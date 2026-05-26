// --- Main Application Logic (User Dashboard) ---
const App = {
    isEditMode: localStorage.getItem('isEditMode') === 'true', // Load from persistence
    draggedComponent: null,
    gridSize: 10,
    currentUserUID: window.APP_CONFIG?.currentUserUID || "",
    activeCharts: {},
    componentIntervals: {},   // kept for graph history refresh only
    componentUpdaters: {},    // map of dataSource -> [updateFn, ...]
    lastKnownData: {},        // last received data snapshot
    isSidebarOpen: localStorage.getItem('sidebarOpen') !== 'false',
    isMobile: window.innerWidth <= 768,
    activeFormIdForTypeSelection: null, // Track which form opened the modal

    API: {
        async fetchData(url) {
            try {
                const cacheBustUrl = url + (url.includes('?') ? '&' : '?') + `_=${new Date().getTime()}`;
                const response = await fetch(cacheBustUrl, { cache: 'no-store' });
                if (!response.ok) {
                    console.error(`HTTP error! Status: ${response.status} for URL: ${url}`);
                    let errorBody = '';
                    try { errorBody = await response.text(); } catch (e) { }
                    console.error('Error body:', errorBody);
                    if (response.status === 403 && errorBody.includes("invalid_or_unknown_uid")) {
                        console.warn("Attempted to access data for an invalid or unknown UID.");
                    }
                    throw new Error(`HTTP error ${response.status}`);
                }
                return response;
            } catch (error) {
                console.error('Fetch API error:', error, "URL:", url);
                throw error;
            }
        },
        async writeValue(uid, dataSourceName, value) {
            if (App.isEditMode) return;
            if (App.Poller._socket && App.Poller._socket.readyState === WebSocket.OPEN) {
                try {
                    App.Poller._socket.send(JSON.stringify({
                        type: 'write',
                        uid: uid,
                        field: dataSourceName,
                        value: value
                    }));
                    return;
                } catch (e) {
                    console.warn("WebSocket write failed, falling back to HTTP:", e);
                }
            }
            try {
                const encodedValue = encodeURIComponent(String(value));
                const response = await App.API.fetchData(`index.php?action=write&UID=${encodeURIComponent(uid)}&${encodeURIComponent(dataSourceName)}=${encodedValue}`);
                const data = await response.text();
                if (data.trim() !== 'success') {
                    console.error('Error writing value:', data, "for", dataSourceName, "with value", value);
                }
            } catch (error) { /* Error already logged by fetchData */ }
        },
        async readValue(uid, dataSourceName, options = {}) {
            if (App.isEditMode && !options.initialLoad) { return ''; }
            try {
                const response = await App.API.fetchData(`index.php?action=read&UID=${encodeURIComponent(uid)}&${encodeURIComponent(dataSourceName)}`);
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("text/plain")) {
                    return await response.text();
                } else if (contentType && contentType.includes("application/json")) {
                    const jsonData = await response.json();
                    return jsonData[dataSourceName] ?? '';
                } else {
                    return await response.text();
                }
            } catch (error) { return ''; }
        },
        async readMultipleValues(uid, fieldsArray, options = {}) {
            if (App.isEditMode && !options.initialLoad) {
                const result = {}; fieldsArray.forEach(field => result[field] = ''); return result;
            }
            if (!fieldsArray || fieldsArray.length === 0) return {};
            const fieldsStr = fieldsArray.map(f => encodeURIComponent(f)).join(',');
            try {
                const response = await App.API.fetchData(`index.php?action=read&UID=${encodeURIComponent(uid)}&fields=${fieldsStr}`);
                return await response.json();
            } catch (error) {
                const errorResult = {}; fieldsArray.forEach(field => errorResult[field] = ''); return errorResult;
            }
        },
        async readHistory(uid, fieldName, options = {}) {
            if (App.isEditMode && !options.initialLoad) return [];
            try {
                const response = await App.API.fetchData(`index.php?action=read_history&UID=${encodeURIComponent(uid)}&field=${encodeURIComponent(fieldName)}`);
                const data = await response.json();
                return Array.isArray(data) ? data : [];
            } catch (error) { return []; }
        }
    },

    UI: {
        toggleSidebar() { App.isSidebarOpen = !App.isSidebarOpen; localStorage.setItem('sidebarOpen', App.isSidebarOpen); App.UI.applySidebarState(); },
        applySidebarState() {
            const mainContainer = document.querySelector('.main-container'); const overlay = document.querySelector('.sidebar-overlay'); if (!mainContainer || !overlay) return;
            if (App.isSidebarOpen) { mainContainer.classList.remove('sidebar-hidden'); overlay.style.display = App.isMobile ? 'block' : 'none'; }
            else { mainContainer.classList.add('sidebar-hidden'); overlay.style.display = 'none'; }
            setTimeout(() => { Object.values(App.activeCharts).forEach(chart => { if (chart && chart.resize) chart.resize(); }); }, 350);
        },
        setInitialSidebarState() {
            App.isMobile = window.innerWidth <= 768;
            if (App.isMobile && localStorage.getItem('sidebarOpen') === null) { App.isSidebarOpen = false; localStorage.setItem('sidebarOpen', 'false'); }
            else { App.isSidebarOpen = localStorage.getItem('sidebarOpen') !== 'false'; }
            App.UI.applySidebarState();
        },
        toggleMode() {
            App.isEditMode = !App.isEditMode;
            localStorage.setItem('isEditMode', App.isEditMode); // Persist
            App.UI.updateModeUI();
            App.initComponents();
        },
        updateModeUI() {
            const dashboard = document.querySelector('.dashboard');
            if (!dashboard) return;
            dashboard.classList.toggle('edit-mode', App.isEditMode);
            dashboard.classList.toggle('play-mode', !App.isEditMode);
            document.body.classList.toggle('play-mode', !App.isEditMode);
            
            const editBtn = document.querySelector('.mode-buttons .edit-mode');
            const playBtn = document.querySelector('.mode-buttons .play-mode');
            if (editBtn) editBtn.classList.toggle('active', App.isEditMode);
            if (playBtn) playBtn.classList.toggle('active', !App.isEditMode);
        },
        makeDraggable(componentEl) {
            let offsetX, offsetY, initialX, initialY;
            const onPointerDown = (e) => {
                if (!App.isEditMode || e.target.closest('.interactive, .delete-btn, canvas') || !e.isPrimary) { return; }
                e.preventDefault(); e.stopPropagation();
                App.draggedComponent = componentEl;
                componentEl.style.touchAction = 'none';
                const compRect = componentEl.getBoundingClientRect();
                offsetX = e.clientX - compRect.left; offsetY = e.clientY - compRect.top;
                initialX = parseInt(componentEl.style.left) || 0; initialY = parseInt(componentEl.style.top) || 0;
                componentEl.style.zIndex = 100;
                componentEl.setPointerCapture(e.pointerId);
                document.addEventListener('pointermove', onPointerMove);
                document.addEventListener('pointerup', onPointerUp);
            };
            const onPointerMove = (ev) => {
                if (!App.draggedComponent || !ev.isPrimary) return; ev.preventDefault();
                const dashElement = componentEl.offsetParent; const dashRect = dashElement.getBoundingClientRect();
                let newX = ev.clientX - dashRect.left - offsetX + dashElement.scrollLeft;
                let newY = ev.clientY - dashRect.top - offsetY + dashElement.scrollTop;
                const maxLeft = dashElement.scrollWidth - componentEl.offsetWidth; const maxTop = dashElement.scrollHeight - componentEl.offsetHeight;
                newX = Math.max(0, Math.min(newX, maxLeft)); newY = Math.max(0, Math.min(newY, maxTop));
                newX = Math.round(newX / App.gridSize) * App.gridSize; newY = Math.round(newY / App.gridSize) * App.gridSize;
                App.draggedComponent.style.left = newX + 'px'; App.draggedComponent.style.top = newY + 'px';
            };
            const onPointerUp = (e) => {
                if (!App.draggedComponent || !e.isPrimary) return;
                componentEl.releasePointerCapture(e.pointerId);
                componentEl.style.zIndex = '';
                componentEl.style.touchAction = 'pan-y';
                const finalX = parseInt(App.draggedComponent.style.left); const finalY = parseInt(App.draggedComponent.style.top);
                if (finalX !== initialX || finalY !== initialY) {
                    // Use window.location.href to ensure project_id is included
                    fetch(window.location.href, {
                        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `update_position=true&id=${App.draggedComponent.dataset.id}&x=${finalX}&y=${finalY}`
                    }).catch(err => console.error("Position update error:", err));
                }
                App.draggedComponent = null;
                document.removeEventListener('pointermove', onPointerMove); document.removeEventListener('pointerup', onPointerUp);
            };
            componentEl.removeEventListener('pointerdown', onPointerDown); componentEl.addEventListener('pointerdown', onPointerDown);
        },
        switchTab(tabName, clickedTabElement) {
            const parentFormContainer = clickedTabElement.closest('.sidebar'); if (!parentFormContainer) return;
            parentFormContainer.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            parentFormContainer.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            clickedTabElement.classList.add('active');
            const activeTabContent = parentFormContainer.querySelector(`#${tabName}-tab`);
            if (activeTabContent) {
                activeTabContent.classList.add('active');
                const form = activeTabContent.querySelector('form');
                if (form) App.UI.updateSidebarFormVisibility(form);
            }
        },
        updateTimeDisplay() {
            const now = new Date();
            const options = { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
            const formatter = new Intl.DateTimeFormat('en-CA', options);
            const parts = formatter.formatToParts(now); const partMap = {}; parts.forEach(p => partMap[p.type] = p.value);
            const datePart = `${partMap.year || '----'}-${partMap.month || '--'}-${partMap.day || '--'}`;
            const timePart = `${partMap.hour || '--'}:${partMap.minute || '--'}:${partMap.second || '--'}`;
            const istString = `${datePart} ${timePart}`;
            const topTimeEl = document.getElementById('top-bar-time-display'); if (topTimeEl) topTimeEl.textContent = `IST: ${istString}`;
            const footTimeEl = document.getElementById('footer-time-display'); if (footTimeEl) footTimeEl.textContent = `IST: ${timePart}`;
        },
        showHelpModal() { document.getElementById('helpModal').style.display = 'flex'; },
        hideHelpModal() { document.getElementById('helpModal').style.display = 'none'; },

        openTypeModal(formId) {
            App.activeFormIdForTypeSelection = formId;
            document.getElementById('type-selection-modal').style.display = 'flex';
        },
        closeTypeModal(event) {
            if (event.target.id === 'type-selection-modal') {
                document.getElementById('type-selection-modal').style.display = 'none';
                App.activeFormIdForTypeSelection = null;
            }
        },
        selectType(typeValue, cardElement) {
            if (App.activeFormIdForTypeSelection) {
                const form = document.getElementById(App.activeFormIdForTypeSelection);
                if (form) {
                    const hiddenInput = form.querySelector('input[name="component_type"]');
                    const trigger = form.querySelector('.type-selector-trigger');

                    hiddenInput.value = typeValue;
                    trigger.innerHTML = cardElement.innerHTML;
                    trigger.classList.remove('placeholder');

                    hiddenInput.dispatchEvent(new Event('change'));
                }
            }
            document.getElementById('type-selection-modal').style.display = 'none';
            App.activeFormIdForTypeSelection = null;
        },
        updateSidebarFormVisibility(form) {
            if (!form) return;
            const compTypeInput = form.querySelector('input[name="component_type"]');
            if (!compTypeInput) return;
            const compType = compTypeInput.value;
            form.querySelectorAll('.toggle-config').forEach(el => el.style.display = (compType === 'toggle') ? 'block' : 'none');
            form.querySelectorAll('.slider-config').forEach(el => el.style.display = (compType === 'slider') ? 'block' : 'none');
            form.querySelectorAll('.interval-config').forEach(el => el.style.display = ['gauge', 'textview', 'graph', 'status'].includes(compType) ? 'block' : 'none');
            form.querySelectorAll('.status-config').forEach(el => el.style.display = (compType === 'status') ? 'block' : 'none');
            form.querySelectorAll('.graph-config').forEach(el => el.style.display = (compType === 'graph') ? 'block' : 'none');
            form.querySelectorAll('.gauge-config').forEach(el => el.style.display = (compType === 'gauge') ? 'block' : 'none');
        },
        setupVariableTabLogic() {
            const varSelect = document.getElementById('var-select');
            const newVarNameContainer = document.getElementById('new-variable-name-container');
            const newVarNameInput = document.getElementById('new-var-name-input');
            if (varSelect && newVarNameContainer && newVarNameInput) {
                varSelect.addEventListener('change', function () {
                    if (this.value === '_new_') {
                        newVarNameContainer.style.display = 'block';
                        newVarNameInput.required = true;
                    } else {
                        newVarNameContainer.style.display = 'none';
                        newVarNameInput.required = false;
                        newVarNameInput.value = '';
                    }
                });
                varSelect.dispatchEvent(new Event('change'));
            }
        }
    },

    Components: {
        handleToggleChange(checkbox, uid) {
            const componentEl = checkbox.closest('.component'); if (!componentEl) return;
            const dataSource = checkbox.dataset.sourceName;
            const value = checkbox.checked ? checkbox.dataset.onValue : checkbox.dataset.offValue;
            App.API.writeValue(uid, dataSource, value);
        },
        handleTextInputSend(uid, targetDataSource, textAreaId) {
            const textArea = document.getElementById(textAreaId);
            if (textArea && textArea.value.trim() !== "") { App.API.writeValue(uid, targetDataSource, textArea.value.trim()); textArea.value = ""; }
        },
        async initToggle(componentEl) {
            const checkbox = componentEl.querySelector('input[type="checkbox"]'); if (!checkbox) return;
            if (App.isEditMode) { checkbox.checked = false; return; }
            const dataSource = checkbox.dataset.sourceName; const onValue = checkbox.dataset.onValue;
            
            const applyToggle = (rawValue) => {
                checkbox.checked = (String(rawValue) === String(onValue));
            };
            
            App.Poller.subscribe(dataSource, applyToggle);
            applyToggle(App.lastKnownData[dataSource] ?? '');
        },
        initGauge(componentEl) {
            const compId = componentEl.dataset.id;
            const selector = `#gauge-${compId}`;
            const gaugeEl = document.querySelector(selector);
            const dataSource = componentEl.dataset.source;
            const unit = gaugeEl ? (gaugeEl.dataset.unit || '') : '';
            
            // Initialize SimpleGauge ONLY if not already initialized
            if (window.gauge && typeof window.gauge.init === 'function') {
                const container = document.querySelector(selector);
                if (container && !container.classList.contains('gauge-initializated')) {
                    window.gauge.init(selector);
                }
            }

            const applyGauge = (rawValue) => {
                let value = parseFloat(rawValue);
                if (isNaN(value)) value = 0;
                
                if (window.gauge && typeof window.gauge.set === 'function') {
                    // Update value and include unit in caption
                    if (gaugeEl) gaugeEl.dataset.caption = `${value.toFixed(1)}${unit}`;
                    window.gauge.set(selector, value);
                }
            };
            
            App.Poller.subscribe(dataSource, applyGauge);
            applyGauge(App.lastKnownData[dataSource] ?? '');
        },
        initTextView(componentEl) {
            const dataSource = componentEl.dataset.source; const compId = componentEl.dataset.id;
            const textElement = document.getElementById(`textview-${compId}`);
            if (!textElement) return;
            const applyText = (rawValue) => {
                textElement.innerText = (rawValue !== '' && rawValue !== undefined) ? rawValue : 'N/A';
            };
            App.Poller.subscribe(dataSource, applyText);
            applyText(App.lastKnownData[dataSource] ?? '');
        },
        async initTextInput(componentEl) {
            const compId = componentEl.dataset.id; const textArea = document.getElementById(`textinput-area-${compId}`);
            if (App.isEditMode && textArea) textArea.value = '';
        },
        initStatusLed(componentEl) {
            const dataSource = componentEl.dataset.source; const compId = componentEl.dataset.id;
            const ledElement = document.getElementById(`status-${compId}`);
            if (!ledElement) return;
            const onColor = ledElement.dataset.onColor || '#2ecc71';
            const offColor = ledElement.dataset.offColor || '#e74c3c';
            const applyLed = (rawValue) => {
                const dataStr = String(rawValue ?? '').trim().toLowerCase();
                const isActive = !(['0', 'false', 'off', 'low', ''].includes(dataStr)) && (isNaN(parseFloat(dataStr)) || parseFloat(dataStr) > 0);
                ledElement.style.backgroundColor = isActive ? onColor : offColor;
            };
            App.Poller.subscribe(dataSource, applyLed);
            applyLed(App.lastKnownData[dataSource] ?? '');
        },
        async initGraph(componentEl) {
            const dataSource = componentEl.dataset.source; const compId = componentEl.dataset.id;
            const canvas = document.getElementById(`graph-${compId}`); const intervalMs = parseInt(componentEl.dataset.interval) * 1000;
            const intervalKey = compId + '_graph'; const graphContainer = componentEl.querySelector('.graph-canvas-container');
            if (!canvas || !graphContainer) return;
            if (App.activeCharts[compId]) App.activeCharts[compId].destroy(); if (App.componentIntervals[intervalKey]) clearInterval(App.componentIntervals[intervalKey]);
            canvas.width = graphContainer.offsetWidth; canvas.height = graphContainer.offsetHeight;
            const ctx = canvas.getContext('2d');

            // Add Reset Zoom Button if not exists
            let resetBtn = graphContainer.querySelector('.reset-zoom-btn');
            if (!resetBtn) {
                resetBtn = document.createElement('button');
                resetBtn.className = 'reset-zoom-btn';
                resetBtn.innerText = 'Reset Zoom';
                resetBtn.style.position = 'absolute';
                resetBtn.style.top = '5px';
                resetBtn.style.right = '5px';
                resetBtn.style.zIndex = '10';
                resetBtn.style.padding = '2px 5px';
                resetBtn.style.fontSize = '10px';
                resetBtn.style.background = 'rgba(255,255,255,0.7)';
                resetBtn.style.border = '1px solid #ccc';
                resetBtn.style.cursor = 'pointer';
                resetBtn.style.display = 'none'; // Hide initially
                resetBtn.onclick = () => {
                    if (App.activeCharts[compId]) App.activeCharts[compId].resetZoom();
                    resetBtn.style.display = 'none';
                };
                graphContainer.style.position = 'relative'; // Ensure container is relative
                graphContainer.appendChild(resetBtn);
            }

            // Add Zoom/Pan Controls if not exists
            let controlsDiv = graphContainer.querySelector('.graph-controls');
            if (!controlsDiv) {
                controlsDiv = document.createElement('div');
                controlsDiv.className = 'graph-controls';
                controlsDiv.style.position = 'absolute';
                controlsDiv.style.bottom = '2px'; // Moved lower
                controlsDiv.style.right = '5px';
                controlsDiv.style.zIndex = '10';
                controlsDiv.style.display = 'flex';
                controlsDiv.style.gap = '2px';

                const createBtn = (text, onClick) => {
                    const btn = document.createElement('button');
                    btn.innerText = text;
                    btn.style.padding = '2px 6px';
                    btn.style.fontSize = '12px';
                    btn.style.cursor = 'pointer';
                    btn.style.background = 'rgba(255,255,255,0.8)';
                    btn.style.border = '1px solid #999';
                    btn.style.borderRadius = '3px';
                    btn.onclick = (e) => {
                        e.stopPropagation(); // Prevent drag
                        onClick();
                        if (resetBtn) resetBtn.style.display = 'block';
                    };
                    return btn;
                };

                controlsDiv.appendChild(createBtn('+', () => { if (App.activeCharts[compId]) App.activeCharts[compId].zoom(1.1); }));
                controlsDiv.appendChild(createBtn('-', () => { if (App.activeCharts[compId]) App.activeCharts[compId].zoom(0.9); }));
                controlsDiv.appendChild(createBtn('<', () => { if (App.activeCharts[compId]) App.activeCharts[compId].pan({ x: 50 }); }));
                controlsDiv.appendChild(createBtn('>', () => { if (App.activeCharts[compId]) App.activeCharts[compId].pan({ x: -50 }); }));

                graphContainer.appendChild(controlsDiv);
            }

            const chartConfig = {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: dataSource,
                        data: [],
                        borderColor: 'var(--primary-color)',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        fill: true,
                        tension: 0.2,
                        pointRadius: 2,
                        pointHoverRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            display: true,
                            ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 7, autoSkipPadding: 10 }
                        },
                        y: {
                            display: true,
                            beginAtZero: false,
                            ticks: { font: { size: 10 } }
                        }
                    },
                    layout: {
                        padding: {
                            bottom: 25 // Add space for buttons
                        }
                    },
                    plugins: {
                        legend: { display: true, position: 'top', labels: { font: { size: 12 } } },
                        tooltip: {
                            callbacks: {
                                title: function (context) {
                                    // Retrieve full timestamp from custom data property if available
                                    const index = context[0].dataIndex;
                                    const chart = context[0].chart;
                                    if (chart.data.fullTimestamps && chart.data.fullTimestamps[index]) {
                                        return chart.data.fullTimestamps[index];
                                    }
                                    return context[0].label;
                                }
                            }
                        },
                        zoom: {
                            pan: {
                                enabled: true,
                                mode: 'x',
                                onPan: () => { if (resetBtn) resetBtn.style.display = 'block'; }
                            },
                            zoom: {
                                wheel: {
                                    enabled: true,
                                },
                                pinch: {
                                    enabled: true
                                },
                                mode: 'x',
                                onZoom: () => { if (resetBtn) resetBtn.style.display = 'block'; }
                            }
                        }
                    },
                    animation: { duration: 0 }
                }
            };
            App.activeCharts[compId] = new Chart(ctx, chartConfig);
            const updateChart = async () => {
                if (App.isEditMode || !App.activeCharts[compId]) {
                    if (App.activeCharts[compId]) { App.activeCharts[compId].data.labels = []; App.activeCharts[compId].data.datasets[0].data = []; App.activeCharts[compId].update('none'); }
                    return;
                }
                const historyData = await App.API.readHistory(App.currentUserUID, dataSource);
                if (!Array.isArray(historyData)) { console.error("Graph history data invalid for", dataSource); return; }
                const labels = historyData.map(entry => { try { const [datePartStr, timePartStr] = (entry.Timestamp || "").split(' '); if (!datePartStr || !timePartStr) return 'Invalid'; const [year, month, day] = datePartStr.split('-').map(Number); const [hour, minute, second] = timePartStr.split(':').map(Number); const dateObject = new Date(year, month - 1, day, hour, minute, second); return dateObject.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }); } catch (e) { return (entry.Timestamp && entry.Timestamp.includes(' ')) ? entry.Timestamp.split(' ')[1] : 'Time?'; } });
                const fullTimestamps = historyData.map(entry => entry.Timestamp || "Unknown Time");
                const values = historyData.map(entry => parseFloat(entry.Value)).filter(v => !isNaN(v));
                if (App.activeCharts[compId]) {
                    App.activeCharts[compId].data.labels = labels;
                    App.activeCharts[compId].data.fullTimestamps = fullTimestamps;
                    App.activeCharts[compId].data.datasets[0].data = values;
                    App.activeCharts[compId].data.datasets[0].label = dataSource;
                    App.activeCharts[compId].update('none');
                }
            };
            // Load history immediately, then refresh whenever server data changes for this source
            await updateChart();
            App.Poller.subscribe(dataSource, () => updateChart());
        },
        async initSlider(componentEl) {
            const sliderDiv = componentEl.querySelector('.custom-slider'); const valueDisplay = componentEl.querySelector('.slider-value-display');
            const dataSource = sliderDiv ? sliderDiv.dataset.sourceName : null; if (!sliderDiv || !valueDisplay || !dataSource) return;
            const track = sliderDiv.querySelector('.slider-track'); const thumb = sliderDiv.querySelector('.slider-thumb'); if (!track || !thumb) return;

            const minValue = parseInt(sliderDiv.dataset.minValue) || 0;
            const maxValue = parseInt(sliderDiv.dataset.maxValue) || 255;

            let currentValue = Math.round((minValue + maxValue) / 2);
            let isDraggingSlider = false;

            function updateThumbDOMPosition(val) {
                const percentage = Math.max(0, Math.min(1, (val - minValue) / (maxValue - minValue)));
                const trackWidth = track.offsetWidth;
                const thumbWidth = thumb.offsetWidth;
                let effectiveTrackWidth = Math.max(0, trackWidth - thumbWidth);
                let thumbLeft = percentage * effectiveTrackWidth;
                thumb.style.left = thumbLeft + 'px';
                valueDisplay.innerText = Math.round(val);
            }

            // Clean up old listeners if they exist on the element itself
            sliderDiv.removeEventListener('pointerdown', sliderDiv._handleStart);
            if (sliderDiv._handleMove) document.removeEventListener('pointermove', sliderDiv._handleMove);
            if (sliderDiv._handleEnd) document.removeEventListener('pointerup', sliderDiv._handleEnd);

            let serverVal = String(Math.round((minValue + maxValue) / 2));
            if (!App.isEditMode) { serverVal = await App.API.readValue(App.currentUserUID, dataSource, { initialLoad: true }); }

            currentValue = parseFloat(serverVal);
            if (isNaN(currentValue) || currentValue < minValue || currentValue > maxValue) { currentValue = Math.round((minValue + maxValue) / 2); }
            updateThumbDOMPosition(currentValue);

            function handleInteractionStart(event) {
                if (App.isEditMode || !event.isPrimary || !sliderDiv.contains(event.target)) return;
                isDraggingSlider = true;
                sliderDiv.setPointerCapture(event.pointerId);
                updateSliderFromEvent(event);
                event.preventDefault();
            }
            function handleInteractionMove(event) {
                if (!isDraggingSlider || !event.isPrimary) return;
                updateSliderFromEvent(event);
                event.preventDefault();
            }
            function handleInteractionEnd(event) {
                if (!isDraggingSlider || !event.isPrimary) return;
                isDraggingSlider = false;
                sliderDiv.releasePointerCapture(event.pointerId);
                if (!App.isEditMode) {
                    App.API.writeValue(App.currentUserUID, dataSource, Math.round(currentValue));
                }
            }
            function updateSliderFromEvent(event) {
                const rect = track.getBoundingClientRect();
                let clientX = event.clientX;
                let relativeX = clientX - rect.left;
                let percentage = Math.max(0, Math.min(1, relativeX / track.offsetWidth));
                currentValue = minValue + percentage * (maxValue - minValue);
                updateThumbDOMPosition(currentValue);
            }

            // Store references for cleanup next time
            sliderDiv._handleStart = handleInteractionStart;
            sliderDiv._handleMove = handleInteractionMove;
            sliderDiv._handleEnd = handleInteractionEnd;

            sliderDiv.addEventListener('pointerdown', handleInteractionStart);
            document.addEventListener('pointermove', handleInteractionMove);
            document.addEventListener('pointerup', handleInteractionEnd);

            const applySlider = (rawValue) => {
                if (isDraggingSlider) return; // Don't snap thumb while user is actively sliding
                const val = parseFloat(rawValue);
                if (!isNaN(val)) {
                    currentValue = val;
                    updateThumbDOMPosition(val);
                }
            };
            App.Poller.subscribe(dataSource, applySlider);
            applySlider(App.lastKnownData[dataSource] ?? '');
        }
    },

    initComponents() {
        // Clear old intervals and charts
        for (const key in App.componentIntervals) { clearInterval(App.componentIntervals[key]); delete App.componentIntervals[key]; }
        for (const chartId in App.activeCharts) { if (App.activeCharts[chartId] && typeof App.activeCharts[chartId].destroy === 'function') { App.activeCharts[chartId].destroy(); } delete App.activeCharts[chartId]; }
        // Reset poller subscriptions (re-registered by each component init below)
        App.componentUpdaters = {};
        // Init each component
        document.querySelectorAll('.component').forEach(compEl => {
            App.UI.makeDraggable(compEl); const type = compEl.dataset.type;
            switch (type) {
                case 'toggle': App.Components.initToggle(compEl); break;
                case 'gauge': App.Components.initGauge(compEl); break;
                case 'textview': App.Components.initTextView(compEl); break;
                case 'status': App.Components.initStatusLed(compEl); break;
                case 'graph': App.Components.initGraph(compEl); break;
                case 'slider': App.Components.initSlider(compEl); break;
                case 'text_input': App.Components.initTextInput(compEl); break;
            }
        });
        // Start or restart the shared poll
        App.Poller.start();
    },

    // --- Smart Poller: ONE shared interval, updates UI only on value change ---
    Poller: {
        _socket: null,
        _reconnectTimeout: null,

        subscribe(dataSource, updateFn) {
            if (!App.componentUpdaters[dataSource]) App.componentUpdaters[dataSource] = [];
            App.componentUpdaters[dataSource].push(updateFn);
        },

        start() {
            if (App.Poller._socket) {
                try { App.Poller._socket.close(); } catch (e) {}
                App.Poller._socket = null;
            }
            if (App.Poller._reconnectTimeout) {
                clearTimeout(App.Poller._reconnectTimeout);
                App.Poller._reconnectTimeout = null;
            }
            if (App.isEditMode || !App.currentUserUID) return;

            const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
            const protocol = (window.location.protocol === 'https:' && !isLocal) ? 'wss:' : 'ws:';
            const wsUrl = isLocal ? `${protocol}//${window.location.hostname}:8080` : `${protocol}//${window.location.hostname}`;

            console.log("Connecting to WebSocket at:", wsUrl);
            const ws = new WebSocket(wsUrl);
            App.Poller._socket = ws;

            ws.onopen = () => {
                console.log("WebSocket connected!");
                ws.send(JSON.stringify({
                    type: 'subscribe',
                    uid: App.currentUserUID
                }));
            };

            ws.onmessage = (event) => {
                try {
                    const msg = JSON.parse(event.data);
                    if (msg.type === 'init') {
                        const data = msg.data || {};
                        Object.keys(data).forEach(field => {
                            const newVal = String(data[field] ?? '');
                            App.lastKnownData[field] = newVal;
                            const updaters = App.componentUpdaters[field] || [];
                            updaters.forEach(fn => { try { fn(newVal); } catch (e) {} });
                        });
                    } else if (msg.type === 'update') {
                        const { field, value } = msg;
                        const newVal = String(value ?? '');
                        const oldVal = String(App.lastKnownData[field] ?? '__UNSET__');
                        if (newVal !== oldVal) {
                            App.lastKnownData[field] = newVal;
                            const updaters = App.componentUpdaters[field] || [];
                            updaters.forEach(fn => { try { fn(newVal); } catch (e) {} });
                        }
                    }
                } catch (e) {
                    console.error("Error processing WebSocket message:", e);
                }
            };

            ws.onclose = () => {
                console.warn("WebSocket disconnected. Reconnecting in 3s...");
                App.Poller._socket = null;
                if (!App.isEditMode) {
                    App.Poller._reconnectTimeout = setTimeout(() => {
                        App.Poller.start();
                    }, 3000);
                }
            };

            ws.onerror = (err) => {
                console.error("WebSocket error:", err);
            };
        },

        stop() {
            if (App.Poller._reconnectTimeout) {
                clearTimeout(App.Poller._reconnectTimeout);
                App.Poller._reconnectTimeout = null;
            }
            if (App.Poller._socket) {
                try { App.Poller._socket.close(); } catch (e) {}
                App.Poller._socket = null;
            }
        }
    },

    startGlobalVoiceCommand(uid) {
        if (App.isEditMode) return;
        const fab = document.getElementById('global-voice-fab');
        const statusDiv = document.getElementById('global-voice-status');

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            statusDiv.innerText = "Speech Recognition not supported in this browser.";
            statusDiv.style.display = 'block';
            setTimeout(() => { statusDiv.style.display = 'none'; }, 3000);
            return;
        }

        const recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'en-US';

        recognition.onstart = function () {
            statusDiv.innerText = "Listening...";
            statusDiv.style.display = 'block';
            fab.classList.add('listening');
        };

        recognition.onresult = function (event) {
            const transcript = event.results[0][0].transcript;
            statusDiv.innerText = `Recognized: "${transcript}"`;
            App.API.writeValue(uid, 'voicecommand', transcript.toLowerCase());
            setTimeout(() => { statusDiv.style.display = 'none'; }, 4000);
        };

        recognition.onerror = function (event) {
            statusDiv.innerText = "Error: " + event.error;
            setTimeout(() => { statusDiv.style.display = 'none'; }, 3000);
        };

        recognition.onend = function () {
            fab.classList.remove('listening');
        };

        recognition.start();
    },

    init() {
        App.UI.setInitialSidebarState();
        // Load persisted mode or default to false
        App.isEditMode = localStorage.getItem('isEditMode') === 'true'; 
        App.UI.updateModeUI();
        App.initComponents();
        App.UI.updateTimeDisplay(); setInterval(App.UI.updateTimeDisplay, 1000);
        App.UI.setupVariableTabLogic();

        document.querySelectorAll('input[name="component_type"]').forEach(input => {
            input.addEventListener('change', function () {
                App.UI.updateSidebarFormVisibility(this.closest('form'));
            });
            App.UI.updateSidebarFormVisibility(input.closest('form'));
        });

        const initialActiveTabButton = document.querySelector('.sidebar .tab.active');
        if (initialActiveTabButton) {
            const initialActiveTabContentId = initialActiveTabButton.dataset.tab + '-tab';
            const initialActiveForm = document.querySelector(`#${initialActiveTabContentId} form`);
            if (initialActiveForm) App.UI.updateSidebarFormVisibility(initialActiveForm);
        }

        window.addEventListener('resize', () => {
            const wasMobile = App.isMobile; App.isMobile = window.innerWidth <= 768;
            if (wasMobile !== App.isMobile) { App.UI.applySidebarState(); }
            setTimeout(() => { Object.values(App.activeCharts).forEach(chart => { if (chart && chart.resize && typeof chart.resize === 'function') { try { chart.resize(); } catch (e) { console.warn("Chart resize error", e); } } }); }, 350);
        });

        const helpBaseUrlSpan = document.querySelector('.help-base-url-span'); if (helpBaseUrlSpan) { helpBaseUrlSpan.textContent = window.location.origin + window.location.pathname; }
    }
};

// --- Admin Panel Logic ---
const AdminApp = {
    keyDataFields: ['UID', 'Timestamp', 'D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7', 'D8', 'A0', 'Temperature', 'Humidity'],
    isSidebarOpenAdmin: localStorage.getItem('sidebarOpenAdmin') !== 'false',
    isMobileAdmin: window.innerWidth <= 768,
    UI: {
        toggleSidebar() { AdminApp.isSidebarOpenAdmin = !AdminApp.isSidebarOpenAdmin; localStorage.setItem('sidebarOpenAdmin', AdminApp.isSidebarOpenAdmin); AdminApp.UI.applySidebarState(); },
        applySidebarState() {
            const mainContainer = document.querySelector('.main-container-admin'); const overlay = document.querySelector('.sidebar-overlay-admin'); if (!mainContainer || !overlay) return;
            if (AdminApp.isSidebarOpenAdmin) { mainContainer.classList.remove('sidebar-hidden'); overlay.style.display = AdminApp.isMobileAdmin ? 'block' : 'none'; }
            else { mainContainer.classList.add('sidebar-hidden'); overlay.style.display = 'none'; }
        },
        setInitialSidebarState() {
            AdminApp.isMobileAdmin = window.innerWidth <= 768;
            if (AdminApp.isMobileAdmin && localStorage.getItem('sidebarOpenAdmin') === null) { AdminApp.isSidebarOpenAdmin = false; localStorage.setItem('sidebarOpenAdmin', 'false'); }
            else { AdminApp.isSidebarOpenAdmin = localStorage.getItem('sidebarOpenAdmin') !== 'false'; }
            AdminApp.UI.applySidebarState();
        }
    },
    updateSingleUserMonitorTable: async (uid) => {
        try {
            const response = await fetch(`index.php?action=read_all&UID=${encodeURIComponent(uid)}&_=${new Date().getTime()}`, { cache: 'no-store' });
            if (!response.ok) throw new Error(`Network response error: ${response.status}`);
            let userData = await response.json();

            // Normalize to array
            if (!Array.isArray(userData)) { userData = [userData]; }

            const table = document.getElementById('userMonitorTable');
            if (!table) return;
            const thead = table.querySelector('thead');
            const tbody = table.querySelector('tbody');
            thead.innerHTML = '';
            tbody.innerHTML = '';

            if (!userData || userData.length === 0 || (userData.length === 1 && userData[0].error)) {
                thead.innerHTML = '<tr><th>Status</th></tr>';
                tbody.innerHTML = '<tr><td style="text-align:center; padding: 15px;">No data available for this user.</td></tr>';
                return;
            }

            // Collect all unique headers
            const allHeaders = new Set();
            userData.forEach(row => {
                if (row && typeof row === 'object') {
                    Object.keys(row).forEach(k => allHeaders.add(k));
                }
            });

            const headersFound = [...allHeaders].filter(key => key !== 'RowType' && key !== 'ProjectID' && key !== 'UID');

            // Define Header Order: ProjectName -> Timestamp -> Key Fields -> Others
            let displayHeaders = [];
            if (allHeaders.has('ProjectName')) displayHeaders.push('ProjectName');
            if (allHeaders.has('Timestamp')) displayHeaders.push('Timestamp');

            const keyFields = AdminApp.keyDataFields.filter(f => f !== 'UID' && f !== 'Timestamp');
            const foundKeyFields = keyFields.filter(h => headersFound.includes(h));
            displayHeaders = [...displayHeaders, ...foundKeyFields];

            const otherHeaders = headersFound.filter(h => !displayHeaders.includes(h)).sort();
            displayHeaders = [...displayHeaders, ...otherHeaders];

            if (displayHeaders.length === 0) {
                thead.innerHTML = '<tr><th>Info</th></tr>';
                tbody.innerHTML = '<tr><td style="text-align:center; padding: 15px;">No data fields with values found.</td></tr>';
                return;
            }

            const trHead = document.createElement('tr');
            displayHeaders.forEach(headerText => {
                const th = document.createElement('th');
                th.textContent = headerText === 'ProjectName' ? 'Project Name' : headerText;
                trHead.appendChild(th);
            });
            thead.appendChild(trHead);

            userData.forEach(row => {
                const tr = document.createElement('tr');
                displayHeaders.forEach(fieldKey => {
                    const td = document.createElement('td');
                    let value = row[fieldKey] ?? '-';
                    if (typeof value === 'string' && value.length > 35) {
                        td.textContent = value.substring(0, 32) + '...';
                        td.title = value;
                    } else {
                        td.textContent = (value === '' || value === null) ? '-' : value;
                    }
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });

        } catch (error) {
            console.error('Error fetching/updating single user monitor table:', error);
            const tbody = document.querySelector('#userMonitorTable tbody');
            if (tbody) tbody.innerHTML = `<tr><td colspan="1" style="text-align:center;color:var(--error-color); padding: 15px;">Error loading live data. Check console.</td></tr>`;
        }
    },
    updateAdminTimeDisplay: () => {
        const now = new Date();
        const options = { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
        const formatter = new Intl.DateTimeFormat('en-CA', options);
        const parts = formatter.formatToParts(now); const partMap = {}; parts.forEach(p => partMap[p.type] = p.value);
        const datePart = `${partMap.year || '----'}-${partMap.month || '--'}-${partMap.day || '--'}`;
        const timePart = `${partMap.hour || '--'}:${partMap.minute || '--'}:${partMap.second || '--'}`;
        const istString = `${datePart} ${timePart}`;
        const topTimeEl = document.getElementById('admin-time-display'); if (topTimeEl) topTimeEl.textContent = `IST: ${istString}`;
        const footTimeEl = document.getElementById('admin-footer-time-display'); if (footTimeEl) footTimeEl.textContent = `IST: ${timePart}`;
    },
    autoHideMessages: () => {
        document.querySelectorAll('.message.auto-hide').forEach(m => {
            setTimeout(() => {
                m.style.transition = 'opacity 0.7s ease-out';
                m.style.opacity = '0';
                setTimeout(() => { m.style.display = 'none'; m.remove(); }, 700);
            }, 5000);
        });
    },
    init: () => {
        AdminApp.UI.setInitialSidebarState();
        AdminApp.updateAdminTimeDisplay();
        setInterval(AdminApp.updateAdminTimeDisplay, 1000);
        AdminApp.autoHideMessages();
        window.addEventListener('resize', () => {
            const wasMobile = AdminApp.isMobileAdmin; AdminApp.isMobileAdmin = window.innerWidth <= 768;
            if (wasMobile !== AdminApp.isMobileAdmin) { AdminApp.UI.applySidebarState(); }
        });
    }
};


// --- Global Initializer ---
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.dashboard')) {
        App.init();
    }
    if (document.querySelector('.content-admin') || document.querySelector('.main-container-admin')) {
        AdminApp.init();
    }
});