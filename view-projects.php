<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Projects - Ninja IoT</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        
        .content-scroll-wrapper {
            flex-grow: 1;
            overflow-y: auto;
            width: 100%;
            background-color: var(--background-color);
        }
        
        .main-container {
            display: block !important;
            padding: 40px 30px !important;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }
        
        .page-header {
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header-text h2 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-color);
            margin: 0 0 5px 0;
            letter-spacing: -0.5px;
        }
        
        .page-header-text p {
            color: var(--text-muted-color);
            margin: 0;
            font-size: 1rem;
            font-weight: 400;
        }

        .projects-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
            gap: 24px; 
            width: 100%;
        }

        .project-card { 
            background: var(--surface-color); 
            padding: 24px; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.03), 0 1px 3px rgba(0,0,0,0.02); 
            transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1); 
            cursor: pointer; 
            text-decoration: none; 
            color: var(--text-color); 
            display: flex; 
            flex-direction: column;
            border: 1px solid var(--border-color); 
            position: relative;
            overflow: hidden;
            min-height: 140px;
        }
        
        .project-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background-color: var(--primary-color);
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .project-card-wrapper {
            position: relative;
            transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .project-card-wrapper:hover { 
            transform: translateY(-4px); 
        }
        
        .project-card-wrapper:hover .project-card {
            box-shadow: 0 12px 24px rgba(0,0,0,0.06); 
            border-color: var(--primary-color);
        }
        
        .project-card-wrapper:hover .project-card::before {
            opacity: 1;
        }

        .project-card h3 { 
            margin-top: 0; 
            color: var(--text-color); 
            margin-bottom: auto;
            font-size: 1.25rem;
            font-weight: 600;
            padding-right: 80px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .project-meta { 
            margin-top: 20px; 
        }
        
        .board-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background-color: var(--surface-darker);
            color: var(--text-color);
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid var(--border-color);
        }

        .board-badge i {
            color: var(--primary-color);
        }

        .add-project-card { 
            border: 2px dashed var(--border-color); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            flex-direction: column; 
            color: var(--text-muted-color); 
            background: transparent; 
            box-shadow: none;
            gap: 12px;
            font-weight: 500;
        }

        .add-project-card:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: var(--surface-color);
        }

        .add-project-card i { 
            font-size: 2rem; 
            transition: transform 0.3s ease;
        }

        .add-project-card:hover i {
            transform: scale(1.1) rotate(90deg);
        }
        
        .project-actions {
            opacity: 0;
            transition: opacity 0.2s ease;
            position: absolute; 
            top: 16px; 
            right: 16px; 
            display: flex; 
            gap: 6px;
            z-index: 10;
        }
        
        .project-card-wrapper:hover .project-actions {
            opacity: 1;
        }
        
        .project-actions button {
            border-radius: 6px;
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            border: 1px solid var(--border-color);
            background-color: var(--surface-color);
            color: var(--text-muted-color);
            cursor: pointer;
            font-size: 13px;
        }
        
        .project-actions .btn-edit:hover { 
            color: var(--primary-color);
            border-color: var(--primary-color);
            background-color: #f0f8ff;
        }
        .project-actions .btn-delete:hover { 
            color: var(--accent-color);
            border-color: var(--accent-color);
            background-color: #fff0f0;
        }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(2px); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }
        .modal-content { background: var(--surface-color); padding: 30px; border-radius: 12px; width: 90%; max-width: 450px; position: relative; border: 1px solid var(--border-color); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15); animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: var(--text-muted-color); transition: color 0.2s; }
        .close-modal:hover { color: var(--text-color); }
        .modal-content h2 { font-size: 1.5rem; font-weight: 600; color: var(--text-color); margin: 0 0 20px 0; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Ensure form inputs match theme */
        input[type="text"], select { width: 100%; padding: 10px 12px; margin-top: 6px; background: var(--surface-darker); border: 1px solid var(--border-color); color: var(--text-color); border-radius: 6px; font-family: 'Inter', sans-serif; transition: all 0.2s; box-sizing: border-box; }
        input[type="text"]:focus, select:focus { outline: none; border-color: var(--primary-color); background: var(--surface-color); box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15); }
        button[type="submit"] { background-color: var(--primary-color); color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 1rem; transition: background-color 0.2s; font-family: 'Inter', sans-serif; }
        button[type="submit"]:hover { background-color: var(--primary-darker); }
        .form-group { margin-bottom: 20px; }
        .form-group label { font-weight: 500; color: var(--text-color); font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">Ninja IoT</div>
        <div class="user-info">User: <strong><?php echo htmlspecialchars($current_user_uid); ?></strong></div>
        <div class="mode-buttons">
            <a href="dashboard?logout=true" class="logout-btn" style="color: white; text-decoration: none; margin-left: 20px; font-size: 0.9em;">Logout</a>
        </div>
    </div>

    <div class="content-scroll-wrapper">
        <div class="main-container">
            <div class="page-header">
                <div class="page-header-text">
                    <h2>My Projects</h2>
                    <p>Manage and monitor your connected devices</p>
                </div>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message" style="margin-bottom: 24px; background: var(--error-color); color: white; padding: 12px; border-radius: 6px;"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="projects-grid">
                <!-- Add Project Card -->
                <div class="project-card add-project-card" onclick="document.getElementById('newProjectModal').classList.add('active')">
                    <i class="fas fa-plus-circle"></i>
                    <span>Create New Project</span>
                </div>

                <?php foreach ($user_projects as $proj): ?>
                <div class="project-card-wrapper">
                    <a href="dashboard?project_id=<?php echo $proj['ProjectID']; ?>" class="project-card">
                        <h3><?php echo htmlspecialchars($proj['Name']); ?></h3>
                        <div class="project-meta">
                            <span class="board-badge"><i class="fas fa-microchip"></i> <?php echo htmlspecialchars($proj['BoardType']); ?></span>
                        </div>
                    </a>
                    <div class="project-actions">
                        <button onclick="openEditModal('<?php echo $proj['ProjectID']; ?>', '<?php echo htmlspecialchars(addslashes($proj['Name'])); ?>')" class="btn-edit" title="Rename"><i class="fas fa-edit"></i></button>
                        <button onclick="openDeleteModal('<?php echo $proj['ProjectID']; ?>', '<?php echo htmlspecialchars(addslashes($proj['Name'])); ?>')" class="btn-delete" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- New Project Modal -->
    <div class="modal-overlay" id="newProjectModal">
        <div class="modal-content">
            <span class="close-modal" onclick="document.getElementById('newProjectModal').classList.remove('active')">&times;</span>
            <h2>Create New Project</h2>
            <form method="post" action="projects">
                <input type="hidden" name="create_project" value="1">
                <div class="form-group">
                    <label style="color: var(--text-color);">Project Name</label>
                    <input type="text" name="project_name" required placeholder="My IoT Project">
                </div>
                <div class="form-group">
                    <label style="color: var(--text-color);">Board Type</label>
                    <select name="board_type">
                        <option value="ESP8266">ESP8266</option>
                        <option value="ESP32">ESP32</option>
                    </select>
                </div>
                <button type="submit" style="width: 100%; margin-top: 20px;">Create Project</button>
            </form>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div class="modal-overlay" id="editProjectModal">
        <div class="modal-content">
            <span class="close-modal" onclick="document.getElementById('editProjectModal').classList.remove('active')">&times;</span>
            <h2>Rename Project</h2>
            <form method="post" action="projects">
                <input type="hidden" name="rename_project" value="1">
                <input type="hidden" name="project_id" id="edit_project_id">
                <div class="form-group">
                    <label style="color: var(--text-color);">New Project Name</label>
                    <input type="text" name="new_project_name" id="edit_project_name" required>
                </div>
                <button type="submit" style="width: 100%; margin-top: 20px;">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Delete Project Modal -->
    <div class="modal-overlay" id="deleteProjectModal">
        <div class="modal-content">
            <span class="close-modal" onclick="document.getElementById('deleteProjectModal').classList.remove('active')">&times;</span>
            <h2 style="color: var(--text-color); margin-top: 0;">Delete Project</h2>
            <p style="color: var(--text-color);">Are you sure you want to delete project <strong id="delete_project_name_display"></strong>?</p>
            <p style="color: #e74c3c; font-size: 0.9em;">This action cannot be undone. All data and events will be lost.</p>
            <form method="post" action="projects">
                <input type="hidden" name="delete_project" value="1">
                <input type="hidden" name="project_id" id="delete_project_id">
                <button type="submit" style="width: 100%; margin-top: 20px; background: #e74c3c;">Delete Project</button>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, name) {
            document.getElementById('edit_project_id').value = id;
            document.getElementById('edit_project_name').value = name;
            document.getElementById('editProjectModal').classList.add('active');
        }
        function openDeleteModal(id, name) {
            document.getElementById('delete_project_id').value = id;
            document.getElementById('delete_project_name_display').textContent = name;
            document.getElementById('deleteProjectModal').classList.add('active');
        }
    </script>
</body>
</html>
