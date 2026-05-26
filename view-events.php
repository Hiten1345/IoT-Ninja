<?php
// Ensure we have access to project data
$project_pins = defined('CURRENT_PROJECT_PINS') ? CURRENT_PROJECT_PINS : PIN_FIELDS_ARRAY;
$project_vars = get_user_custom_variables($project_id);
$all_vars = array_merge(DEFAULT_DATA_VARIABLES_IN_CSV, $project_vars);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <title>Events - <?php echo htmlspecialchars($current_project['Name']); ?></title>
    <script src="https://unpkg.com/blockly/blockly.min.js"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { margin: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .events-header { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; background: var(--bg-color); color: var(--text-color); border-bottom: 1px solid var(--border-color); }
        #blocklyDiv { flex-grow: 1; width: 100%; }
        button { padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer; font-weight: bold; }
        .save-btn { background: var(--accent-color); color: white; }
        .back-btn { background: #95a5a6; color: white; text-decoration: none; display: inline-block; padding: 10px 20px; border-radius: 5px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="events-header">
        <div>
            <h2>Event Logic: <?php echo htmlspecialchars($current_project['Name']); ?></h2>
            <small>Define logic. Triggers on data updates.</small>
        </div>
        <div>
            <button onclick="saveEvents()" class="save-btn">Save Logic</button>
            <a href="dashboard?project_id=<?php echo $project_id; ?>" class="back-btn">Back to Dashboard</a>
        </div>
    </div>
    <div id="blocklyDiv"></div>

    <xml xmlns="https://developers.google.com/blockly/xml" id="toolbox" style="display: none">
        <category name="Rules" colour="#333">
            <block type="iot_rule"></block>
        </category>
        <category name="Logic" colour="#5C81A6">
            <block type="action_if"></block>
            <block type="logic_boolean"></block>
            <block type="trigger_compare"></block>
        </category>
        <category name="Loops" colour="#5CA65C">
            <block type="action_repeat"></block>
        </category>
        <category name="Triggers" colour="#d35400">
            <block type="trigger_compare"></block>
            <block type="trigger_pin_compare"></block>
            <block type="trigger_variable_compare"></block>
            <block type="trigger_voice_command"></block>
        </category>
        <category name="Actions" colour="#27ae60">
            <block type="action_send_email_external"></block>
            <block type="action_set_pin"></block>
            <block type="action_set_variable"></block>
            <block type="action_http_request"></block>
            <block type="action_wait"></block>
        </category>
        <category name="Values" colour="#2980b9">
            <block type="value_get_time"></block>
            <block type="value_get_date"></block>
            <block type="value_get_variable"></block>
            <block type="math_number"></block>
            <block type="text"></block>
            <block type="value_text_join"></block>
        </category>
        <category name="Math" colour="#8e44ad">
            <block type="math_arithmetic_custom"></block>
        </category>
        <category name="Conversion" colour="#f39c12">
            <block type="type_conversion"></block>
        </category>
    </xml>

    <script>
        // --- Initialize JSON Generator ---
        const jsonGenerator = new Blockly.Generator('JSON');
        jsonGenerator.PRECEDENCE = 0;

        // Pass PHP data to JS
        const PROJECT_PINS = <?php echo json_encode($project_pins); ?>;
        const PROJECT_VARS = <?php echo json_encode($all_vars); ?>;

        // --- Block Definitions ---
        Blockly.Blocks['iot_rule'] = {
            init: function() {
                this.appendValueInput("TRIGGER")
                    .setCheck("Trigger")
                    .appendField("IF");
                this.appendStatementInput("DO")
                    .setCheck(null)
                    .appendField("THEN DO");
                this.setColour(210);
                this.setTooltip("Define a rule with a trigger and actions.");
                this.setHelpUrl("");
            }
        };

        Blockly.Blocks['trigger_compare'] = {
            init: function() {
                this.appendValueInput("VAL1")
                    .setCheck(["Number", "String", "Boolean", "JSON"])
                    .appendField("Value");
                this.appendValueInput("VAL2")
                    .setCheck(["Number", "String", "Boolean", "JSON"])
                    .appendField(new Blockly.FieldDropdown([["Equal to","EQ"], ["Greater than","GT"], ["Less than","LT"], ["Not Equal","NEQ"]]), "OP");
                this.setOutput(true, "Trigger");
                this.setColour(20);
                this.setTooltip("Compares two values.");
            }
        };

        Blockly.Blocks['value_get_time'] = {
            init: function() {
                this.appendDummyInput()
                    .appendField("Current Time (HH:MM)");
                this.setOutput(true, "String");
                this.setColour(230);
                this.setTooltip("Returns current server time (IST).");
            }
        };

        Blockly.Blocks['value_get_date'] = {
            init: function() {
                this.appendDummyInput()
                    .appendField("Current Date (YYYY-MM-DD)");
                this.setOutput(true, "String");
                this.setColour(230);
                this.setTooltip("Returns current server date.");
            }
        };

        Blockly.Blocks['value_get_variable'] = {
            init: function() {
                var varOptions = PROJECT_VARS.length > 0 ? PROJECT_VARS.map(v => [v, v]) : [['No Vars', '']];
                this.appendDummyInput()
                    .appendField("Get Variable")
                    .appendField(new Blockly.FieldDropdown(varOptions), "VAR");
                this.setOutput(true, "JSON");
                this.setColour(230);
                this.setTooltip("Returns the current value of a variable.");
            }
        };

        Blockly.Blocks['trigger_pin_compare'] = {
            init: function() {
                var pinOptions = PROJECT_PINS.map(p => [p, p]);
                this.appendDummyInput()
                    .appendField("Pin")
                    .appendField(new Blockly.FieldDropdown(pinOptions), "PIN")
                    .appendField("is")
                    .appendField(new Blockly.FieldDropdown([["Equal to","EQ"], ["Greater than","GT"], ["Less than","LT"]]), "OP");
                this.appendValueInput("VALUE")
                    .setCheck(["Number", "String", "Boolean", "JSON"])
                    .appendField("Value");
                this.setOutput(true, "Trigger");
                this.setColour(20);
                this.setTooltip("Triggers when a pin value matches a condition.");
            }
        };

        Blockly.Blocks['trigger_variable_compare'] = {
            init: function() {
                var varOptions = PROJECT_VARS.length > 0 ? PROJECT_VARS.map(v => [v, v]) : [['No Vars', '']];
                this.appendDummyInput()
                    .appendField("Variable")
                    .appendField(new Blockly.FieldDropdown(varOptions), "VAR")
                    .appendField("is")
                    .appendField(new Blockly.FieldDropdown([["Equal to","EQ"], ["Greater than","GT"], ["Less than","LT"]]), "OP");
                this.appendValueInput("VALUE")
                    .setCheck(["Number", "String", "Boolean", "JSON"])
                    .appendField("Value");
                this.setOutput(true, "Trigger");
                this.setColour(20);
                this.setTooltip("Triggers when a variable value matches a condition.");
            }
        };

        Blockly.Blocks['trigger_voice_command'] = {
            init: function() {
                this.appendDummyInput()
                    .appendField("Voice Command")
                    .appendField(new Blockly.FieldDropdown([["Contains","CONTAINS"], ["Exact Match","EQ"]]), "OP");
                this.appendValueInput("VALUE")
                    .setCheck(["String"])
                    .appendField("Text");
                this.setOutput(true, "Trigger");
                this.setColour(20);
                this.setTooltip("Triggers when a global voice command matches the text.");
            }
        };

        Blockly.Blocks['action_send_email_external'] = {
            init: function() {
                this.appendDummyInput()
                    .appendField("Send Email");
                this.appendValueInput("TO")
                    .setCheck("String")
                    .appendField("To");
                this.appendValueInput("SUBJECT")
                    .setCheck("String")
                    .appendField("Subject");
                this.appendValueInput("BODY")
                    .setCheck("String")
                    .appendField("Body");
                this.appendDummyInput()
                    .appendField("From (Gmail):")
                    .appendField(new Blockly.FieldTextInput("user@gmail.com"), "SMTP_USER");
                this.appendDummyInput()
                    .appendField("App Password:")
                    .appendField(new Blockly.FieldTextInput("xxxx xxxx xxxx xxxx"), "SMTP_PASS");
                this.setPreviousStatement(true, null);
                this.setNextStatement(true, null);
                this.setColour(120);
                this.setTooltip("Sends an email using external inputs.");
            }
        };
        
        Blockly.Blocks['action_set_pin'] = {
            init: function() {
                var pinOptions = PROJECT_PINS.map(p => [p, p]);
                this.appendDummyInput()
                    .appendField("Set Pin")
                    .appendField(new Blockly.FieldDropdown(pinOptions), "PIN");
                this.appendValueInput("VALUE")
                    .setCheck(["Number", "String", "Boolean", "JSON"])
                    .appendField("To Value");
                this.setPreviousStatement(true, null);
                this.setNextStatement(true, null);
                this.setColour(120);
                this.setTooltip("Sets a pin value.");
            }
        };

        Blockly.Blocks['action_set_variable'] = {
            init: function() {
                var varOptions = PROJECT_VARS.length > 0 ? PROJECT_VARS.map(v => [v, v]) : [['No Vars', '']];
                this.appendDummyInput()
                    .appendField("Set Variable")
                    .appendField(new Blockly.FieldDropdown(varOptions), "VAR");
                this.appendValueInput("VALUE")
                    .setCheck(["Number", "String", "Boolean", "JSON"])
                    .appendField("To Value");
                this.setPreviousStatement(true, null);
                this.setNextStatement(true, null);
                this.setColour(120);
                this.setTooltip("Sets a variable value.");
            }
        };

        // --- NEW BLOCKS ---

        Blockly.Blocks['math_arithmetic_custom'] = {
            init: function() {
                this.appendValueInput("A")
                    .setCheck(["Number", "JSON"])
                    .appendField("Math");
                this.appendValueInput("B")
                    .setCheck(["Number", "JSON"])
                    .appendField(new Blockly.FieldDropdown([["+","ADD"], ["-","SUB"], ["×","MUL"], ["÷","DIV"], ["%","MOD"]]), "OP");
                this.setOutput(true, "JSON");
                this.setColour(230);
                this.setTooltip("Perform arithmetic operations.");
            }
        };

        Blockly.Blocks['type_conversion'] = {
            init: function() {
                this.appendValueInput("VAL")
                    .setCheck(null)
                    .appendField("Convert");
                this.appendDummyInput()
                    .appendField("to")
                    .appendField(new Blockly.FieldDropdown([["Number","number"], ["String","string"], ["Boolean","boolean"]]), "TYPE");
                this.setOutput(true, "JSON");
                this.setColour(230);
                this.setTooltip("Convert value type.");
            }
        };

        Blockly.Blocks['action_http_request'] = {
            init: function() {
                var varOptions = PROJECT_VARS.length > 0 ? PROJECT_VARS.map(v => [v, v]) : [['No Vars', '']];
                varOptions.unshift(['(Do not save)', '']);
                
                this.appendDummyInput()
                    .appendField("HTTP Request")
                    .appendField(new Blockly.FieldDropdown([["GET","GET"], ["POST","POST"], ["PUT","PUT"], ["DELETE","DELETE"]]), "METHOD");
                this.appendValueInput("URL")
                    .setCheck("String")
                    .appendField("URL");
                this.appendValueInput("BODY")
                    .setCheck("String")
                    .appendField("Body (Optional)");
                this.appendDummyInput()
                    .appendField("Save Response To:")
                    .appendField(new Blockly.FieldDropdown(varOptions), "SAVE_TO");
                this.setPreviousStatement(true, null);
                this.setNextStatement(true, null);
                this.setColour(60);
                this.setTooltip("Make an HTTP request and optionally save the response.");
            }
        };

        Blockly.Blocks['action_if'] = {
            init: function() {
                this.appendValueInput("IF")
                    .setCheck(["Boolean", "Trigger", "JSON"])
                    .appendField("If");
                this.appendStatementInput("DO")
                    .setCheck(null)
                    .appendField("Then");
                this.appendStatementInput("ELSE")
                    .setCheck(null)
                    .appendField("Else");
                this.setPreviousStatement(true, null);
                this.setNextStatement(true, null);
                this.setColour(210);
                this.setTooltip("If-Else logic.");
            }
        };

        Blockly.Blocks['action_repeat'] = {
            init: function() {
                this.appendValueInput("COUNT")
                    .setCheck(["Number", "JSON"])
                    .appendField("Repeat");
                this.appendDummyInput()
                    .appendField("times");
                this.appendStatementInput("DO")
                    .setCheck(null)
                    .appendField("Do");
                this.setPreviousStatement(true, null);
                this.setNextStatement(true, null);
                this.setColour(120);
                this.setTooltip("Repeat actions N times.");
            }
        };

        Blockly.Blocks['action_wait'] = {
            init: function() {
                this.appendValueInput("SECONDS")
                    .setCheck(["Number", "JSON"])
                    .appendField("Wait");
                this.appendDummyInput()
                    .appendField("seconds");
                this.setPreviousStatement(true, null);
                this.setNextStatement(true, null);
                this.setColour(120);
                this.setTooltip("Wait for a specified time.");
            }
        };

        Blockly.Blocks['value_text_join'] = {
            init: function() {
                this.appendValueInput("A")
                    .setCheck(null)
                    .appendField("Join");
                this.appendValueInput("B")
                    .setCheck(null)
                    .appendField("and");
                this.setOutput(true, "String");
                this.setColour(160);
                this.setTooltip("Join two strings.");
            }
        };

        // --- JSON Generator Functions ---
        jsonGenerator.forBlock['iot_rule'] = function(block) {
            const trigger = jsonGenerator.valueToCode(block, 'TRIGGER', jsonGenerator.PRECEDENCE) || 'null';
            let statements_do = jsonGenerator.statementToCode(block, 'DO');
            statements_do = statements_do.trim();
            if (statements_do.endsWith(',')) {
                statements_do = statements_do.slice(0, -1);
            }
            return `{"type": "rule", "trigger": ${trigger}, "actions": [${statements_do}]},\n`;
        };

        jsonGenerator.forBlock['trigger_compare'] = function(block) {
            const val1 = jsonGenerator.valueToCode(block, 'VAL1', jsonGenerator.PRECEDENCE) || '""';
            const op = block.getFieldValue('OP');
            const val2 = jsonGenerator.valueToCode(block, 'VAL2', jsonGenerator.PRECEDENCE) || '""';
            return [`{"type": "compare", "val1": ${val1}, "op": "${op}", "val2": ${val2}}`, jsonGenerator.PRECEDENCE];
        };

        jsonGenerator.forBlock['value_get_time'] = function(block) {
            return ['"_CURRENT_TIME_"', jsonGenerator.PRECEDENCE];
        };

        jsonGenerator.forBlock['value_get_date'] = function(block) {
            return ['"_CURRENT_DATE_"', jsonGenerator.PRECEDENCE];
        };

        jsonGenerator.forBlock['value_get_variable'] = function(block) {
            const variable = block.getFieldValue('VAR');
            const code = `{"type": "get_var", "name": "${variable}"}`;
            return [code, jsonGenerator.PRECEDENCE];
        };

        jsonGenerator.forBlock['trigger_pin_compare'] = function(block) {
            const pin = block.getFieldValue('PIN');
            const op = block.getFieldValue('OP');
            const value = jsonGenerator.valueToCode(block, 'VALUE', jsonGenerator.PRECEDENCE) || '0';
            return [`{"type": "pin_compare", "pin": "${pin}", "op": "${op}", "value": ${value}}`, jsonGenerator.PRECEDENCE];
        };

        jsonGenerator.forBlock['trigger_variable_compare'] = function(block) {
            const variable = block.getFieldValue('VAR');
            const op = block.getFieldValue('OP');
            const value = jsonGenerator.valueToCode(block, 'VALUE', jsonGenerator.PRECEDENCE) || '0';
            return [`{"type": "var_compare", "variable": "${variable}", "op": "${op}", "value": ${value}}`, jsonGenerator.PRECEDENCE];
        };

        jsonGenerator.forBlock['trigger_voice_command'] = function(block) {
            const op = block.getFieldValue('OP');
            const value = jsonGenerator.valueToCode(block, 'VALUE', jsonGenerator.PRECEDENCE) || '""';
            return [`{"type": "voice_command", "op": "${op}", "value": ${value}}`, jsonGenerator.PRECEDENCE];
        };

        jsonGenerator.forBlock['action_send_email_external'] = function(block) {
            const to = jsonGenerator.valueToCode(block, 'TO', jsonGenerator.PRECEDENCE) || '""';
            const subject = jsonGenerator.valueToCode(block, 'SUBJECT', jsonGenerator.PRECEDENCE) || '""';
            const body = jsonGenerator.valueToCode(block, 'BODY', jsonGenerator.PRECEDENCE) || '""';
            const smtp_user = JSON.stringify(block.getFieldValue('SMTP_USER'));
            const smtp_pass = JSON.stringify(block.getFieldValue('SMTP_PASS'));
            return `{"type": "email", "to": ${to}, "subject": ${subject}, "body": ${body}, "smtp_user": ${smtp_user}, "smtp_pass": ${smtp_pass}},`;
        };
        
        jsonGenerator.forBlock['action_set_pin'] = function(block) {
            const pin = block.getFieldValue('PIN');
            const value = jsonGenerator.valueToCode(block, 'VALUE', jsonGenerator.PRECEDENCE) || '0';
            return `{"type": "set_pin", "pin": "${pin}", "value": ${value}},`;
        };

        jsonGenerator.forBlock['action_set_variable'] = function(block) {
            const variable = block.getFieldValue('VAR');
            const value = jsonGenerator.valueToCode(block, 'VALUE', jsonGenerator.PRECEDENCE) || '0';
            return `{"type": "set_variable", "variable": "${variable}", "value": ${value}},`;
        };

        jsonGenerator.forBlock['math_number'] = function(block) {
            const code = String(block.getFieldValue('NUM'));
            return [code, jsonGenerator.PRECEDENCE];
        };

        jsonGenerator.forBlock['text'] = function(block) {
            const code = JSON.stringify(block.getFieldValue('TEXT'));
            return [code, jsonGenerator.PRECEDENCE];
        };
        
        jsonGenerator.forBlock['logic_boolean'] = function(block) {
            const code = (block.getFieldValue('BOOL') == 'TRUE') ? 'true' : 'false';
            return [code, jsonGenerator.PRECEDENCE];
        };

        // --- NEW GENERATORS ---

        jsonGenerator.forBlock['math_arithmetic_custom'] = function(block) {
            const a = jsonGenerator.valueToCode(block, 'A', jsonGenerator.PRECEDENCE) || '0';
            const b = jsonGenerator.valueToCode(block, 'B', jsonGenerator.PRECEDENCE) || '0';
            const op = block.getFieldValue('OP');
            // Return a JSON object string that the PHP backend will parse recursively
            const code = `{"type": "math", "op": "${op}", "val1": ${a}, "val2": ${b}}`;
            return [code, jsonGenerator.PRECEDENCE];
        };

        jsonGenerator.forBlock['type_conversion'] = function(block) {
            const val = jsonGenerator.valueToCode(block, 'VAL', jsonGenerator.PRECEDENCE) || '""';
            const type = block.getFieldValue('TYPE');
            const code = `{"type": "convert", "to": "${type}", "value": ${val}}`;
            return [code, jsonGenerator.PRECEDENCE];
        };

        jsonGenerator.forBlock['action_http_request'] = function(block) {
            const method = block.getFieldValue('METHOD');
            const url = jsonGenerator.valueToCode(block, 'URL', jsonGenerator.PRECEDENCE) || '""';
            const body = jsonGenerator.valueToCode(block, 'BODY', jsonGenerator.PRECEDENCE) || '""';
            const saveTo = block.getFieldValue('SAVE_TO');
            return `{"type": "http_request", "method": "${method}", "url": ${url}, "body": ${body}, "save_to": "${saveTo}"},`;
        };

        jsonGenerator.forBlock['action_if'] = function(block) {
            const condition = jsonGenerator.valueToCode(block, 'IF', jsonGenerator.PRECEDENCE) || 'false';
            let statements_do = jsonGenerator.statementToCode(block, 'DO');
            let statements_else = jsonGenerator.statementToCode(block, 'ELSE');
            
            if (statements_do.trim().endsWith(',')) statements_do = statements_do.trim().slice(0, -1);
            if (statements_else.trim().endsWith(',')) statements_else = statements_else.trim().slice(0, -1);
            
            return `{"type": "if", "condition": ${condition}, "then": [${statements_do}], "else": [${statements_else}]},`;
        };

        jsonGenerator.forBlock['action_repeat'] = function(block) {
            const count = jsonGenerator.valueToCode(block, 'COUNT', jsonGenerator.PRECEDENCE) || '0';
            let statements_do = jsonGenerator.statementToCode(block, 'DO');
            if (statements_do.trim().endsWith(',')) statements_do = statements_do.trim().slice(0, -1);
            
            return `{"type": "repeat", "count": ${count}, "do": [${statements_do}]},`;
        };

        jsonGenerator.forBlock['action_wait'] = function(block) {
            const seconds = jsonGenerator.valueToCode(block, 'SECONDS', jsonGenerator.PRECEDENCE) || '0';
            return `{"type": "wait", "seconds": ${seconds}},`;
        };

        jsonGenerator.forBlock['value_text_join'] = function(block) {
            const a = jsonGenerator.valueToCode(block, 'A', jsonGenerator.PRECEDENCE) || '""';
            const b = jsonGenerator.valueToCode(block, 'B', jsonGenerator.PRECEDENCE) || '""';
            return [`{"type": "join", "parts": [${a}, ${b}]}`, jsonGenerator.PRECEDENCE];
        };
        
        jsonGenerator.scrub_ = function(block, code, opt_thisOnly) {
            const nextBlock = block.nextConnection && block.nextConnection.targetBlock();
            const nextCode = opt_thisOnly ? '' : jsonGenerator.blockToCode(nextBlock);
            return code + nextCode;
        };

        // --- Initialize ---
        const workspace = Blockly.inject('blocklyDiv', {
            toolbox: document.getElementById('toolbox'),
            scrollbars: true,
            zoom: { controls: true, wheel: true }
        });

        // Load existing XML if any
        <?php
            $xml_file = DATA_DIR . "events_ui_" . $project_id . ".xml";
            if (file_exists($xml_file)) {
                $xml_content = file_get_contents($xml_file);
                $xml_content_js = json_encode($xml_content);
                echo "const existingXml = $xml_content_js;";
            } else {
                echo "const existingXml = '';";
            }
        ?>
        
        if (existingXml) {
            const parser = new DOMParser();
            const xmlDom = parser.parseFromString(existingXml, "text/xml");
            Blockly.Xml.domToWorkspace(xmlDom.documentElement, workspace);
        }

        function saveEvents() {
            try {
                let code = jsonGenerator.workspaceToCode(workspace);
                code = code.trim();
                if (code.endsWith(',')) code = code.slice(0, -1);
                const json = `[${code}]`;
                
                // Validate JSON
                try {
                    JSON.parse(json);
                } catch (e) {
                    alert("Generated JSON is invalid: " + e + "\nCode: " + json);
                    return;
                }

                const xmlDom = Blockly.Xml.workspaceToDom(workspace);
                const xmlText = Blockly.Xml.domToText(xmlDom);

                const formData = new FormData();
                formData.append('save_events', '1');
                formData.append('events_json', json);
                formData.append('events_xml', xmlText);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                }).then(response => response.text())
                  .then(data => {
                      if (data.includes('success')) alert('Events saved successfully!');
                      else alert('Failed to save events: ' + data);
                  })
                  .catch(err => alert("Network error: " + err));
            } catch (e) {
                alert("Error generating code: " + e);
            }
        }
    </script>
</body>
</html>
