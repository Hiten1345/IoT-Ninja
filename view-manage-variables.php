<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Variables - Ninja IoT</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Roboto, Arial, sans-serif;
        }
        .variables-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .top-bar {
            background-color: #3498db;
            color: white;
            padding: 15px 20px;
            margin: -20px -20px 20px -20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .top-bar h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }
        .top-bar .project-info {
            font-size: 13px;
            opacity: 0.9;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            transition: background 0.2s;
        }
        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .action-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        .action-bar h2 {
            margin: 0;
            font-size: 18px;
            color: #2c3e50;
        }
        .btn-create {
            padding: 8px 16px;
            background: #2ecc71;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-create:hover {
            background: #27ae60;
        }
        .btn-create i {
            font-size: 14px;
        }
        .error-message, .success-message {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .error-message {
            background: #fee;
            color: #c33;
            border-left: 4px solid #e74c3c;
        }
        .success-message {
            background: #efe;
            color: #383;
            border-left: 4px solid #2ecc71;
        }
        .variables-list {
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .variable-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s;
        }
        .variable-item:last-child {
            border-bottom: none;
        }
        .variable-item:hover {
            background: #f8f9fa;
        }
        .variable-name {
            font-size: 15px;
            font-weight: 500;
            color: #2c3e50;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .variable-name i {
            color: #3498db;
            font-size: 16px;
        }
        .variable-actions {
            display: flex;
            gap: 8px;
        }
        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-edit {
            background: #3498db;
            color: white;
        }
        .btn-edit:hover {
            background: #2980b9;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        .btn-delete:hover {
            background: #c0392b;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .empty-state p {
            font-size: 15px;
            margin: 0;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 6px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        .modal-content h2 {
            margin: 0 0 20px 0;
            font-size: 20px;
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        .form-group small {
            color: #7f8c8d;
            font-size: 12px;
            display: block;
            margin-top: 4px;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .btn-cancel {
            padding: 8px 16px;
            background: #ecf0f1;
            color: #2c3e50;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
            font-size: 14px;
        }
        .btn-cancel:hover {
            background: #d5dbdb;
        }
        .btn-submit {
            padding: 8px 16px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
            font-size: 14px;
        }
        .btn-submit:hover {
            background: #2980b9;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .variables-container {
                padding: 15px;
            }
            .top-bar {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            .action-bar {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            .btn-create {
                width: 100%;
                justify-content: center;
            }
            .variable-item {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            .variable-actions {
                width: 100%;
            }
            .btn-edit, .btn-delete {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="variables-container">
        <div class="top-bar">
            <div>
                <h1>Manage Variables</h1>
                <div class="project-info">Project: <strong><?php echo htmlspecialchars($current_project['Name']); ?></strong></div>
            </div>
            <a href="dashboard?project_id=<?php echo htmlspecialchars($project_id); ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (!empty($manage_var_error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($manage_var_error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($manage_var_success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($manage_var_success); ?></div>
        <?php endif; ?>
        
        <div class="action-bar">
            <h2>Custom Variables</h2>
            <button class="btn-create" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> Create New Variable
            </button>
        </div>
        
        <div class="variables-list">
            <?php if (empty($custom_variables)): ?>
                <div class="empty-state">
                    <i class="fas fa-code"></i>
                    <p>No custom variables yet. Click "Create New Variable" to get started!</p>
                </div>
            <?php else: ?>
                <?php foreach ($custom_variables as $var): ?>
                    <div class="variable-item">
                        <span class="variable-name">
                            <i class="fas fa-database"></i>
                            <?php echo htmlspecialchars($var); ?>
                        </span>
                        <div class="variable-actions">
                            <button class="btn-edit" onclick="openRenameModal('<?php echo htmlspecialchars($var, ENT_QUOTES); ?>')">
                                <i class="fas fa-edit"></i> Rename
                            </button>
                            <button class="btn-delete" onclick="confirmDelete('<?php echo htmlspecialchars($var, ENT_QUOTES); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Rename Modal -->
    <div class="modal-overlay" id="renameModal">
        <div class="modal-content">
            <h2>Rename Variable</h2>
            <form method="post" action="manage-variables?project_id=<?php echo htmlspecialchars($project_id); ?>">
                <input type="hidden" name="rename_variable" value="1">
                <input type="hidden" name="old_name" id="rename-old-name">
                <div class="form-group">
                    <label for="new-name">New Variable Name:</label>
                    <input type="text" name="new_name" id="new-name" required pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores allowed">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeRenameModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Rename</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create Variable Modal -->
    <div class="modal-overlay" id="createModal">
        <div class="modal-content">
            <h2>Create New Variable</h2>
            <form method="post" action="manage-variables?project_id=<?php echo htmlspecialchars($project_id); ?>">
                <input type="hidden" name="create_variable" value="1">
                <div class="form-group">
                    <label for="create-var-name">Variable Name:</label>
                    <input type="text" name="variable_name" id="create-var-name" required pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores allowed" placeholder="e.g., Temperature, MySensor">
                    <small style="color: var(--text-muted-color); font-size: 12px; display: block; margin-top: 5px;">
                        Only letters, numbers, and underscores allowed
                    </small>
                </div>
                <div class="form-group">
                    <label for="create-initial-value">Initial Value (optional):</label>
                    <input type="text" name="initial_value" id="create-initial-value" placeholder="Leave empty or enter a value">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Create</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openRenameModal(varName) {
            document.getElementById('rename-old-name').value = varName;
            document.getElementById('new-name').value = varName;
            document.getElementById('renameModal').style.display = 'flex';
        }
        
        function closeRenameModal() {
            document.getElementById('renameModal').style.display = 'none';
        }
        
        function openCreateModal() {
            document.getElementById('create-var-name').value = '';
            document.getElementById('create-initial-value').value = '';
            document.getElementById('createModal').style.display = 'flex';
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }
        
        function confirmDelete(varName) {
            if (confirm(`Are you sure you want to delete the variable "${varName}"?\n\nThis will also remove all dashboard components using this variable.`)) {
                window.location.href = `manage-variables?project_id=<?php echo htmlspecialchars($project_id); ?>&delete_variable=${encodeURIComponent(varName)}`;
            }
        }
        
        // Close modals when clicking outside
        document.getElementById('renameModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRenameModal();
            }
        });
        
        document.getElementById('createModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateModal();
            }
        });
    </script>
</body>
</html>
