<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($current_user_uid); ?> - Ninja IoT</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
    <link rel="stylesheet" href="SimpleGauge-main/gauge.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@1.2.1/dist/chartjs-plugin-zoom.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <script src="SimpleGauge-main/gauge.js"></script>
</head>
<body>
    <div class="top-bar">
        <button class="sidebar-toggle" onclick="App.UI.toggleSidebar()" title="Toggle Sidebar">☰</button>
        <div class="logo">Ninja IoT</div>
        <div class="user-info">
            <a href="projects" style="color: white; margin-right: 15px; text-decoration: none;"><i class="fas fa-arrow-left"></i> Projects</a>
            Project: <strong><?php echo htmlspecialchars($current_project['Name']); ?></strong> (<?php echo htmlspecialchars($current_project['BoardType']); ?>)
        </div>
        <div class="mode-buttons">
            <button class="edit-mode" onclick="App.UI.toggleMode()">Edit</button>
            <button class="play-mode active" onclick="App.UI.toggleMode()">Play</button>
        </div>
    </div>

    <div class="main-container">
        <div class="sidebar">
            <h3>Add Component</h3>
             <?php if (!empty($dashboard_error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($dashboard_error_message); ?></div>
            <?php endif; ?>
            <div class="tabs">
                <button class="tab active" data-tab="pin" onclick="App.UI.switchTab('pin', this)">Pin</button>
                <button class="tab" data-tab="variable" onclick="App.UI.switchTab('variable', this)">Var</button>
            </div>

            <!-- PIN TAB FORM -->
            <div id="pin-tab" class="tab-content active">
                <form method="post" action="dashboard?project_id=<?php echo htmlspecialchars($project_id); ?>" id="pin-form">
                    <input type="hidden" name="create_component" value="1">
                    <input type="hidden" name="source_type" value="pin">
                    <div class="form-group">
                        <label for="pin-select">Pin:</label>
                        <select name="pin" id="pin-select" required>
                            <option value="">-- Select Pin --</option>
                            <?php foreach ($PIN_VARIABLES_FOR_UI as $pin_var): ?>
                            <option value="<?php echo htmlspecialchars($pin_var); ?>"><?php echo htmlspecialchars($pin_var); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                  <div class="form-group">
                    <label for="pin-comp-type-trigger">Type:</label>
                    <!-- --- MODIFIED --- This is now a button to trigger the modal -->
                    <div id="pin-comp-type-trigger" class="type-selector-trigger placeholder" tabindex="0" onclick="App.UI.openTypeModal('pin-form')">
                        -- Select Type --
                    </div>
                    <!-- This hidden input will hold the actual value -->
                    <input type="hidden" name="component_type" id="pin-comp-type-hidden" value="" required>
                  </div>
                    
                    <!-- Common Component Config Fields for this Form -->
                    <?php include 'view-dashboard-form-configs.php'; ?>
                    
                    <button type="submit">Create</button>
                </form>
            </div>

            <!-- VARIABLE TAB FORM -->
            <div id="variable-tab" class="tab-content">
                <form method="post" action="dashboard?project_id=<?php echo htmlspecialchars($project_id); ?>" id="variable-form">
                    <input type="hidden" name="create_component" value="1">
                    <input type="hidden" name="source_type" value="variable">
                    <div class="form-group">
                        <label for="var-select">Variable:</label>
                        <select name="variable_selection" id="var-select" required>
                            <option value="">-- Select or Create Variable --</option>
                            <?php foreach ($variables_for_variable_tab_dropdown as $cv): ?>
                            <option value="<?php echo htmlspecialchars($cv); ?>"><?php echo htmlspecialchars($cv); ?></option>
                            <?php endforeach; ?>
                            <option value="_new_">Create New Variable...</option>
                        </select>
                    </div>
                    <div class="form-group" id="new-variable-name-container" style="display: none;">
                        <label for="new-var-name-input">New Variable Name:</label>
                        <input type="text" name="new_variable_name" id="new-var-name-input" placeholder="E.g., MySensor" list="suggested-vars-datalist">
                        <datalist id="suggested-vars-datalist">
                            <?php foreach ($SUGGESTED_VARIABLES_FOR_UI_DASH as $s_var): ?>
                            <option value="<?php echo htmlspecialchars($s_var); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                   <div class="form-group">
                        <label for="var-comp-type-trigger">Type:</label>
                        <!-- --- MODIFIED --- This is now a button to trigger the modal -->
                        <div id="var-comp-type-trigger" class="type-selector-trigger placeholder" tabindex="0" onclick="App.UI.openTypeModal('variable-form')">
                             -- Select Type --
                        </div>
                        <!-- This hidden input will hold the actual value -->
                        <input type="hidden" name="component_type" id="var-comp-type-hidden" value="" required>
                    </div>
                    
                    <!-- Common Component Config Fields for this Form -->
                    <?php include 'view-dashboard-form-configs.php'; ?>
                    
                    <button type="submit">Create</button>
                </form>
            </div>


            <div class="sidebar-actions">
                <a href="javascript:void(0)" onclick="App.UI.showHelpModal()">API Help</a>
                <a href="events?project_id=<?php echo $project_id; ?>" class="events-btn" style="background: #e67e22; color: white;">Events & Logic</a>
                <a href="manage-variables?project_id=<?php echo $project_id; ?>" style="background: #9b59b6; color: white;">Manage Variables</a>
                <a href="index.php?action=download_history&UID=<?php echo htmlspecialchars($current_user_uid); ?>" class="download-history-sidebar">Download History</a>
                <a href="dashboard?logout=true" class="logout-btn">Logout</a>
            </div>

        </div>

        <div class="sidebar-overlay" onclick="App.UI.toggleSidebar()"></div>

        <div class="dashboard edit-mode">
            <?php foreach ($user_components as $component):
                $dataSource = !empty($component['pin']) ? $component['pin'] : (!empty($component['variable']) ? $component['variable'] : '');
                $displayName = !empty($component['display_name']) ? $component['display_name'] : '';
                
                if (!empty($displayName)) {
                    $displayLabelContent = $displayName . ' (' . $dataSource . ')';
                } else {
                    $displayLabelContent = trim($dataSource) === '' ? '(No Source)' : $dataSource;
                }
                $displayLabel = htmlspecialchars($displayLabelContent);
            
                $component_id_attr = htmlspecialchars($component['id']);
                $component_type_class = htmlspecialchars($component['type']);
                $component_x = intval($component['x'] ?? 10);
                $component_y = intval($component['y'] ?? 10);
                $component_style = "left: {$component_x}px; top: {$component_y}px;";
                if ($component['type'] === 'graph') {
                     $graphW = intval($component['graph_width'] ?? 300);
                     $component_style .= "width: {$graphW}px;";
                } elseif ($component['type'] === 'text_input') {
                    $component_style .= "width: 220px; min-height: 140px;";
                }
                $interval_value = intval($component['interval'] ?? 5);
            ?>
                <div class="component <?php echo $component_type_class; ?>"
                     data-id="<?php echo $component_id_attr; ?>"
                     style="<?php echo $component_style; ?>"
                     data-source="<?php echo htmlspecialchars($dataSource); ?>"
                     data-interval="<?php echo $interval_value; ?>"
                     data-type="<?php echo $component_type_class; ?>">

                    <div class="component-label"><?php echo $displayLabel; ?></div>
                    <a href="dashboard?project_id=<?php echo $project_id; ?>&delete_component=<?php echo $component_id_attr; ?>" class="delete-btn" onclick="return confirm('Delete this component?')">×</a>

                    <?php switch($component['type']):
                        case 'toggle':
                            $on_val = htmlspecialchars($component['on_value'] ?? '1');
                            $off_val = htmlspecialchars($component['off_value'] ?? '0');
                    ?>
                        <label class="toggle-switch interactive">
                            <input type="checkbox" id="toggle-<?php echo $component_id_attr; ?>"
                                   data-source-name="<?php echo htmlspecialchars($dataSource); ?>"
                                   data-on-value="<?php echo $on_val; ?>"
                                   data-off-value="<?php echo $off_val; ?>"
                                   onchange="App.Components.handleToggleChange(this, '<?php echo $current_user_uid; ?>')">
                            <span class="slider"></span>
                        </label>
                    <?php break; case 'gauge': 
                            $g_min = htmlspecialchars($component['gauge_min'] ?? '0');
                            $g_max = htmlspecialchars($component['gauge_max'] ?? '100');
                            $g_unit = htmlspecialchars($component['gauge_units'] ?? '%');
                    ?>
                        <div class="gauge-simple-wrapper">
                            <div id="gauge-<?php echo $component_id_attr; ?>" 
                                 class="gauge-container-instance"
                                 data-min="<?php echo $g_min; ?>" 
                                 data-max="<?php echo $g_max; ?>" 
                                 data-unit="<?php echo $g_unit; ?>"
                                 data-initial="0">
                            </div>
                        </div>
                    <?php break; case 'slider': 
                            $min_val = htmlspecialchars($component['min_value'] ?? '0');
                            $max_val = htmlspecialchars($component['max_value'] ?? '255');
                    ?>
                        <div class="custom-slider-container interactive">
                            <div class="custom-slider" id="slider-<?php echo $component_id_attr; ?>" 
                                 data-source-name="<?php echo htmlspecialchars($dataSource); ?>"
                                 data-min-value="<?php echo $min_val; ?>"
                                 data-max-value="<?php echo $max_val; ?>">
                                <div class="slider-track"></div>
                                <div class="slider-thumb"></div>
                            </div>
                            <span class="slider-value-display" id="slider-value-<?php echo $component_id_attr; ?>">128</span>
                        </div>
                    <?php break; case 'status': ?>
                        <span class="status-led" id="status-<?php echo $component_id_attr; ?>"
                              data-on-color="<?php echo htmlspecialchars($component['on_color'] ?? '#2ecc71'); ?>"
                              data-off-color="<?php echo htmlspecialchars($component['off_color'] ?? '#e74c3c'); ?>"></span>
                    <?php break; case 'textview': ?>
                        <span class="textview-value" id="textview-<?php echo $component_id_attr; ?>">N/A</span>
                    <?php break; case 'text_input': ?>
                        <div class="text-input-container interactive">
                            <textarea id="textinput-area-<?php echo $component_id_attr; ?>" class="interactive" placeholder="Enter message..."></textarea>
                            <button class="interactive"
                                    onclick="App.Components.handleTextInputSend('<?php echo $current_user_uid; ?>', '<?php echo htmlspecialchars($dataSource); ?>', 'textinput-area-<?php echo $component_id_attr; ?>')">
                                Send
                            </button>
                        </div>
                    <?php break; case 'graph':
                            $graphW = intval($component['graph_width'] ?? 300);
                            $graphH = intval($component['graph_height'] ?? 200);
                        ?>
                        <div class="graph-canvas-container" style="width: <?php echo $graphW; ?>px; height: <?php echo $graphH; ?>px;">
                             <canvas id="graph-<?php echo $component_id_attr; ?>"></canvas>
                        </div>
                    <?php break; default: ?>
                         <p style="color: var(--accent-color); font-size: 12px;">Unknown Type</p>
                    <?php endswitch; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="footer">
        <span id="footer-time-display">IST: Loading...</span>
        <span>&copy; <?php echo date('Y');?> Ninja IoT</span>
    </div>

    <div class="modal-overlay" id="helpModal">
        <div class="modal-content">
            <h2>API Endpoint Examples</h2>
            <p><strong>Project API Key:</strong> <code><?php echo $current_project['APIKey']; ?></code></p>
            <p>Base URL: <code><?php echo APP_BASE_URL; ?>api/v1/<?php echo $current_project['APIKey']; ?>/</code></p>
            
            <h3>Write Value:</h3>
            <pre><?php echo APP_BASE_URL; ?>api/v1/<?php echo $current_project['APIKey']; ?>/write?D1=10
<?php echo APP_BASE_URL; ?>api/v1/<?php echo $current_project['APIKey']; ?>/write?Temperature=25.5</pre>
            
            <h3>Read Specific Value (Plain Text):</h3>
            <pre><?php echo APP_BASE_URL; ?>api/v1/<?php echo $current_project['APIKey']; ?>/read?D1
<?php echo APP_BASE_URL; ?>api/v1/<?php echo $current_project['APIKey']; ?>/read?MyCustomVar</pre>
            
            <h3>Read Multiple Specific Values (JSON):</h3>
            <pre><?php echo APP_BASE_URL; ?>api/v1/<?php echo $current_project['APIKey']; ?>/read?fields=D0,Temperature,Timestamp</pre>
            
            <h3>Read All Latest Values (JSON):</h3>
            <pre><?php echo APP_BASE_URL; ?>api/v1/<?php echo $current_project['APIKey']; ?>/read_all</pre>

            <h3>History Specific Values (JSON): single or multiple</h3>
            <pre><?php echo APP_BASE_URL; ?>api/v1/<?php echo $current_project['APIKey']; ?>/history?fields=D0,Temperature</pre>
            
            <button class="modal-close-btn" onclick="App.UI.hideHelpModal()">Close</button>
        </div>
    </div>

    <!-- --- NEW --- This is the modal for selecting component type -->
    <div class="type-selection-modal" id="type-selection-modal" onclick="App.UI.closeTypeModal(event)">
        <div class="modal-content">
            <h2>Select Component Type</h2>
            <div class="modal-grid">
                <div class="type-card" onclick="App.UI.selectType('toggle', this)">
                    <i class="fas fa-toggle-on"></i>
                    <span>Toggle</span>
                </div>
                <div class="type-card" onclick="App.UI.selectType('slider', this)">
                    <i class="fas fa-sliders-h"></i>
                    <span>Slider</span>
                </div>
                <div class="type-card" onclick="App.UI.selectType('gauge', this)">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Gauge</span>
                </div>
                <div class="type-card" onclick="App.UI.selectType('textview', this)">
                    <i class="fas fa-file-alt"></i>
                    <span>Text View</span>
                </div>
                 <div class="type-card" onclick="App.UI.selectType('status', this)">
                    <i class="fas fa-lightbulb"></i>
                    <span>Status LED</span>
                </div>
                <div class="type-card" onclick="App.UI.selectType('graph', this)">
                    <i class="fas fa-chart-line"></i>
                    <span>Graph</span>
                </div>
                <div class="type-card" onclick="App.UI.selectType('text_input', this)">
                    <i class="fas fa-keyboard"></i>
                    <span>Text Input</span>
                </div>
            </div>
        </div>
    </div>


    <!-- Global Voice Command FAB -->
    <div id="global-voice-fab" class="voice-fab" onclick="App.startGlobalVoiceCommand('<?php echo $current_user_uid; ?>')">
        <i class="fas fa-microphone"></i>
    </div>
    <div id="global-voice-status" class="voice-status"></div>

    <style>
        .voice-fab {
            position: fixed;
            bottom: 65px;
            right: 40px;
            width: 60px;
            height: 60px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: none; /* hidden by default, shown in play mode */
            justify-content: center;
            align-items: center;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            cursor: pointer;
            z-index: 1000;
            transition: transform 0.3s, background-color 0.3s;
        }
        
        /* Show in play mode */
        body.play-mode #global-voice-fab {
            display: flex;
        }

        .voice-fab:hover {
            transform: scale(1.1);
        }

        .voice-fab.listening {
            background-color: var(--accent-color);
            animation: pulse 1.5s infinite;
        }

        .voice-status {
            position: fixed;
            bottom: 135px;
            right: 40px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: none; /* hidden by default */
            z-index: 1000;
        }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 15px rgba(231, 76, 60, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }
    </style>

    <script>
        // Pass PHP variables to the global window object for scripts.js to use
        window.APP_CONFIG = {
            currentUserUID: "<?php echo htmlspecialchars($current_user_uid); ?>"
        };
    </script>
    <script src="scripts.js?v=<?php echo filemtime('scripts.js'); ?>"></script>
</body>
</html>