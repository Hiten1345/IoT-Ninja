<div class="form-group">
    <label for="display_name">Display Name (Label):</label>
    <input type="text" name="display_name" placeholder="e.g., Fan, Light, etc.">
    <small style="color: #7f8c8d; font-size: 11px;">Optional: Custom label for the component.</small>
</div>

<div class="form-group toggle-config" style="display: none;">
    <label for="on_value">On Value:</label>
    <input type="text" name="on_value" value="1" placeholder="e.g., 1 or HIGH">
</div>
<div class="form-group toggle-config" style="display: none;">
    <label for="off_value">Off Value:</label>
    <input type="text" name="off_value" value="0" placeholder="e.g., 0 or LOW">
</div>

<div class="form-group slider-config" style="display: none;">
    <label for="min_value">Min Value:</label>
    <input type="number" name="min_value" value="0" placeholder="e.g., 0">
</div>
<div class="form-group slider-config" style="display: none;">
    <label for="max_value">Max Value:</label>
    <input type="number" name="max_value" value="255" placeholder="e.g., 255">
</div>

<div class="form-group interval-config" style="display: none;">
    <label for="interval">Update Interval (sec):</label>
    <input type="number" name="interval" value="5" min="4" placeholder="Min 4 seconds">
</div>

<div class="form-group status-config" style="display: none;">
    <label for="on_color">On Color:</label>
    <input type="color" name="on_color" value="#2ecc71">
</div>
<div class="form-group status-config" style="display: none;">
    <label for="off_color">Off Color:</label>
    <input type="color" name="off_color" value="#e74c3c">
</div>

<div class="form-group graph-config" style="display: none;">
    <label for="graph_width">Graph Width (px):</label>
    <input type="number" name="graph_width" value="300" min="150" max="800">
</div>
<div class="form-group graph-config" style="display: none;">
    <label for="graph_height">Graph Height (px):</label>
    <input type="number" name="graph_height" value="200" min="100" max="500">
</div>

<!-- GAUGE CONFIG -->
<div class="form-group gauge-config" style="display: none;">
    <label for="gauge_min">Min Value:</label>
    <input type="number" name="gauge_min" value="0" placeholder="e.g. 0">
</div>
<div class="form-group gauge-config" style="display: none;">
    <label for="gauge_max">Max Value:</label>
    <input type="number" name="gauge_max" value="100" placeholder="e.g. 100">
</div>
<div class="form-group gauge-config" style="display: none;">
    <label for="gauge_units">Units:</label>
    <input type="text" name="gauge_units" value="%" placeholder="e.g. %, °C, V">
</div>