<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --secondary: #64748b;
            --success: #10b981;
            --info: #06b6d4;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f1f5f9;
            --dark: #1e293b;
            --body-bg: #f8fafc;
            --body-color: #334155;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --nav-height: 70px;
            --border-radius: 8px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--body-bg);
            color: var(--body-color);
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .navbar {
            background: var(--card-bg);
            border-bottom: 1px solid var(--card-border);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--card-shadow);
            height: var(--nav-height);
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 2rem;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 3rem;
            flex: 1;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            padding: 0.5rem;
        }
        
        .logo::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 0;
            width: 0;
            height: 3px;
            background-color: var(--primary);
            transition: var(--transition);
        }
        
        .logo:hover::after {
            width: 100%;
        }

        .logo i {
            font-size: 1.75rem;
            color: var(--primary);
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-item {
            position: relative;
        }
        
        .nav-link {
            color: var(--secondary);
            text-decoration: none;
            padding: 0.75rem 1.25rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .nav-link:hover {
            color: var(--primary);
            background-color: var(--light);
            border-color: var(--card-border);
            transform: translateY(-2px);
        }

        .nav-link.active {
            color: var(--primary);
            background-color: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.2);
        }

        .dropdown {
            position: relative;
        }
        
        .dropdown-btn {
            background: none;
            border: 1px solid transparent;
            color: var(--secondary);
            padding: 0.75rem 1.25rem;
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.625rem;
            white-space: nowrap;
            font-family: inherit;
        }

        .dropdown-btn:hover {
            color: var(--primary);
            background-color: var(--light);
            border-color: var(--card-border);
            transform: translateY(-2px);
        }
        
        .dropdown-arrow {
            transition: var(--transition);
            font-size: 0.75rem;
            color: var(--secondary);
            margin-left: 0.25rem;
        }

        .dropdown.open .dropdown-btn {
            color: var(--primary);
            background-color: var(--light);
            border-color: var(--card-border);
        }
        
        .dropdown.open .dropdown-arrow {
            transform: rotate(180deg);
            color: var(--primary);
        }
        
        .dropdown-content {
            position: absolute;
            top: calc(100% + 0.5rem);
            left: 0;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--border-radius);
            min-width: 220px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-0.5rem);
            transition: var(--transition);
            box-shadow: var(--card-shadow);
            padding: 0.5rem;
            z-index: 1001;
        }

        .dropdown.open .dropdown-content {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            color: var(--secondary);
            text-decoration: none;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.95rem;
            white-space: nowrap;
            border-radius: calc(var(--border-radius) - 2px);
        }

        .dropdown-item:hover {
            color: var(--primary);
            background-color: var(--light);
            transform: translateX(4px);
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex: 1;
        }

        .user-menu {
            position: relative;
            margin-left: auto;
        }

        .user-btn {
            background: none;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .user-btn:hover {
            background-color: var(--light);
            border-color: var(--card-border);
        }
        
        .dropdown.open .user-btn {
            background-color: var(--light);
            border-color: var(--card-border);
        }
        
        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            transition: var(--transition);
        }
        
        .user-btn:hover .user-avatar {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            transform: scale(1.05);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--dark);
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--secondary);
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.95rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
        }
        
        /* Icon styles */
        .icon {
            font-size: 1.125rem;
            margin-right: 0.375rem;
            transition: var(--transition);
        }
        
        .dropdown-item:hover .icon,
        .nav-link:hover .icon,
        .dropdown-btn:hover .icon {
            transform: scale(1.1);
            color: var(--primary);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-left">
                <a href="dashboard.php" class="logo"><i class="bi bi-"></i> JobBeta2</a>
                
                <div class="nav-links">
                    <a href="dashboard.php" class="nav-link">
                        <i class="bi bi-bar-chart-fill icon"></i>
                        Dashboard
                    </a>
                    
                    <a href="application-detail2.php" class="nav-link">
                        <i class="bi bi-file-earmark-person icon"></i>
                        Başvuru Detayları
                    </a>
                    
                    <div class="dropdown" id="statisticsDropdown">
                        <button class="dropdown-btn" onclick="toggleDropdown('statisticsDropdown')">
                            <i class="bi bi-graph-up icon"></i>
                            İstatistikler
                            <i class="bi bi-chevron-down dropdown-arrow"></i>
                        </button>
                        <div class="dropdown-content">
                            <a href="application-statistics.php" class="dropdown-item">
                                <i class="bi bi-graph-up icon"></i>
                                İstatistikler
                            </a>
                            <a href="application-statistics2.php" class="dropdown-item">
                                <i class="bi bi-graph-up-arrow icon"></i>
                                İstatistikler 2
                            </a>
                        </div>
                    </div>

                    <div class="dropdown" id="jobsDropdown">
                        <button class="dropdown-btn" onclick="toggleDropdown('jobsDropdown')">
                            <i class="bi bi-briefcase icon"></i>
                            İş İlanları
                            <i class="bi bi-chevron-down dropdown-arrow"></i>
                        </button>
                        <div class="dropdown-content">
                            <a href="manage-jobs.php" class="dropdown-item">
                                <i class="bi bi-gear icon"></i>
                                İş İlanlarını Yönet
                            </a>
                            <a href="create-job.php" class="dropdown-item">
                                <i class="bi bi-plus-circle icon"></i>
                                İş İlanı Oluştur
                            </a>

                            <a href="manage-job-questions.php" class="dropdown-item">
                                <i class="bi bi-question-circle icon"></i>
                                Soruları Yönet
                            </a>
                        </div>
                    </div>

                    <div class="dropdown" id="templatesDropdown">
                        <button class="dropdown-btn" onclick="toggleDropdown('templatesDropdown')">
                            <i class="bi bi-clipboard icon"></i>
                            Şablonlar
                            <i class="bi bi-chevron-down dropdown-arrow"></i>
                        </button>
                        <div class="dropdown-content">
                            <a href="manage-templates.php" class="dropdown-item">
                                <i class="bi bi-gear icon"></i>
                                Şablonları Yönet
                            </a>
                            <a href="create-template.php" class="dropdown-item">
                                <i class="bi bi-plus-circle icon"></i>
                                Şablon Oluştur
                            </a>                        </div>
                    </div>
                </div>

            </div>
            <div class="nav-right">
                <div class="user-menu dropdown" id="userDropdown">
                    <button class="user-btn" onclick="toggleDropdown('userDropdown')">
                        <div class="user-avatar">A</div>                        
                        <div class="user-info">
                            <div class="user-name">Admin</div>
                        </div>
                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                    </button>                
                    <div class="dropdown-content" style="right: 0; left: auto;">
                        <a href="logout.php" class="dropdown-item" style="color: #dc2626;">
                            <i class="bi bi-box-arrow-right icon"></i>
                            Çıkış Yap
                        </a>
                    </div>                
                </div>
            </div>
        </div>    </nav>
    
    <script>
        // Dropdown functionality
        let activeDropdown = null;

        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            
            // Close other dropdowns
            if (activeDropdown && activeDropdown !== dropdown) {
                activeDropdown.classList.remove('open');
            }
            
            // Toggle current dropdown
            dropdown.classList.toggle('open');
            activeDropdown = dropdown.classList.contains('open') ? dropdown : null;
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                if (activeDropdown) {
                    activeDropdown.classList.remove('open');
                    activeDropdown = null;
                }
            }
        });

        // Prevent dropdown from closing when clicking inside
        document.querySelectorAll('.dropdown-content').forEach(content => {
            content.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (activeDropdown) {
                    activeDropdown.classList.remove('open');
                    activeDropdown = null;
                }
            }
        });
    </script>
</body>
</html>