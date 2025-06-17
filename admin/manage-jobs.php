<?php
session_start();

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/db.php';

$success_message = '';
$error_message = '';

// URL'den gelen mesajları kontrol et
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success_message = "İş ilanı başarıyla oluşturuldu.";
            break;
        case 'updated':
            $success_message = "İş ilanı başarıyla güncellendi.";
            break;
        case 'deleted':
            $success_message = "İş ilanı başarıyla silindi.";
            break;
    }
}

if (isset($_GET['error'])) {
    $error_message = "İşlem sırasında bir hata oluştu.";
}

// İş ilanı silme işlemi
if (isset($_POST['delete_job']) && !empty($_POST['job_id'])) {
    $job_id = (int)$_POST['job_id'];
    
    try {
        $db->beginTransaction();
        
        // Önce job_template_configs tablosundan kayıtları sil
        $stmt = $db->prepare("DELETE FROM job_template_configs WHERE job_id = :job_id");
        $stmt->bindParam(':job_id', $job_id);
        $stmt->execute();
        
        // Soruları sil - önce seçenekleri sil
        $option_stmt = $db->prepare("
            DELETE FROM options 
            WHERE question_id IN (SELECT id FROM questions WHERE job_id = :job_id)
        ");
        $option_stmt->bindParam(':job_id', $job_id);
        $option_stmt->execute();
        
        // Soruları sil
        $stmt = $db->prepare("DELETE FROM questions WHERE job_id = :job_id");
        $stmt->bindParam(':job_id', $job_id);
        $stmt->execute();
        
        // Başvurulara ait cevapları sil
        $stmt = $db->prepare("
            DELETE FROM application_answers 
            WHERE application_id IN (SELECT id FROM applications WHERE job_id = :job_id)
        ");
        $stmt->bindParam(':job_id', $job_id);
        $stmt->execute();
        
        // Eski cevapları sil (answers tablosu)
        $stmt = $db->prepare("
            DELETE FROM answers 
            WHERE application_id IN (SELECT id FROM applications WHERE job_id = :job_id)
        ");
        $stmt->bindParam(':job_id', $job_id);
        $stmt->execute();
        
        // Başvuruları sil
        $stmt = $db->prepare("DELETE FROM applications WHERE job_id = :job_id");
        $stmt->bindParam(':job_id', $job_id);
        $stmt->execute();
        
        // İş ilanını sil
        $stmt = $db->prepare("DELETE FROM jobs WHERE id = :job_id");
        $stmt->bindParam(':job_id', $job_id);
        
        if ($stmt->execute()) {
            $db->commit();
            header("Location: manage-jobs.php?success=deleted");
            exit;
        } else {
            throw new Exception("İş ilanı silinirken bir hata oluştu.");
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error_message = "Hata: " . $e->getMessage();
    }
}

// İş ilanı durumu güncelleme
if (isset($_POST['toggle_status']) && !empty($_POST['job_id'])) {
    $job_id = (int)$_POST['job_id'];
    $new_status = $_POST['new_status'];
    
    try {
        $stmt = $db->prepare("UPDATE jobs SET status = :status WHERE id = :job_id");
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':job_id', $job_id);
        
        if ($stmt->execute()) {
            $success_message = "İş ilanı durumu güncellendi.";
        } else {
            throw new Exception("Durum güncellenirken bir hata oluştu.");
        }
    } catch (Exception $e) {
        $error_message = "Hata: " . $e->getMessage();
    }
}

// Sayfalama ayarları
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 9; // 3x3 grid için
$offset = ($page - 1) * $per_page;

// Filtreleme
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// WHERE koşulları
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "j.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(j.title LIKE :search OR j.location LIKE :search OR j.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Toplam kayıt sayısını al
$count_sql = "SELECT COUNT(*) FROM jobs j $where_clause";
$count_stmt = $db->prepare($count_sql);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// İş ilanlarını getir
$sql = "
    SELECT 
        j.id,
        j.title,
        j.description,
        j.location,
        j.status,
        j.created_at,
        (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) as application_count,
        (SELECT COUNT(*) FROM questions q WHERE q.job_id = j.id) as question_count
    FROM jobs j 
    $where_clause
    ORDER BY j.created_at DESC 
    LIMIT :offset, :per_page
";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Her iş ilanı için kullanılan şablonları al
foreach ($jobs as &$job) {
    $template_sql = "
        SELECT 
            qt.id as template_id,
            qt.template_name,
            jtc.question_count
        FROM job_template_configs jtc
        JOIN question_templates qt ON jtc.template_id = qt.id
        WHERE jtc.job_id = :job_id
        ORDER BY qt.template_name
    ";
    $template_stmt = $db->prepare($template_sql);
    $template_stmt->bindParam(':job_id', $job['id']);
    $template_stmt->execute();
    $job['templates'] = $template_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// İstatistikler
$active_count_stmt = $db->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'");
$active_count = $active_count_stmt->fetchColumn();

$inactive_count_stmt = $db->query("SELECT COUNT(*) FROM jobs WHERE status = 'inactive'");
$inactive_count = $inactive_count_stmt->fetchColumn();

$app_count_stmt = $db->query("SELECT COUNT(*) FROM applications");
$application_count = $app_count_stmt->fetchColumn();

$template_count_stmt = $db->query("SELECT COUNT(*) FROM question_templates");
$template_count = $template_count_stmt->fetchColumn();

// Son bir aydaki başvuru sayısı
$month_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
$recent_app_count_stmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE created_at >= :month_ago");
$recent_app_count_stmt->bindParam(':month_ago', $month_ago);
$recent_app_count_stmt->execute();
$recent_application_count = $recent_app_count_stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İş İlanları Yönetimi | İş Başvuru Sistemi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4361ee;
            --primary-hover: #3a56d4;
            --secondary: #747f8d;
            --success: #2ecc71;
            --info: #3498db;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #f5f7fb;
            --dark: #343a40;
            --body-bg: #f9fafb;
            --body-color: #333;
            --card-bg: #ffffff;
            --card-border: #eaedf1;
            --card-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
            --transition-normal: all 0.2s ease-in-out;
        }
        
        body {
            background-color: var(--body-bg);
            color: var(--body-color);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 0.9rem;
        }
        
        /* Navbar Styles */
        .navbar {
            background-color: #ffffff !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            padding: 0.75rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary) !important;
        }
        
        .navbar-nav .nav-link {
            color: #6c757d;
            padding: 0.75rem 1rem;
            position: relative;
        }
        
        .navbar-nav .nav-link.active {
            color: var(--primary);
            font-weight: 600;
        }
        
        .navbar-nav .nav-link.active:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 1rem;
            right: 1rem;
            height: 2px;
            background-color: var(--primary);
        }
        
        .navbar-nav .nav-link:hover {
            color: var(--primary);
        }
        
        /* Page Header */
        .page-header {
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--card-border);
            background-color: #fff;
        }
        
        .page-title {
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
        }
        
        .page-subtitle {
            color: var(--secondary);
            font-weight: 400;
            margin-bottom: 0;
        }
        
        /* Card Styles */
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid var(--card-border);
            padding: 1rem 1.25rem;
            font-weight: 600;
        }
        
        .card-title {
            margin-bottom: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Job Card Styles */
        .job-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            background-color: #fff;
            height: 100%;
        }
        
        .job-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .job-card.active {
            border-left-color: #10b981;
        }
        
        .job-card.inactive {
            border-left-color: #6b7280;
        }
        
        .job-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 0.5rem;
        }
        
        .job-card .location {
            display: flex;
            align-items: center;
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }
        
        .job-card .location i {
            margin-right: 0.5rem;
            font-size: 1rem;
        }
        
        .job-description {
            max-height: 3.6em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            margin-bottom: 10px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Template Tags */
        .template-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 8px;
        }
        
        .template-tag {
            background: #e7f3ff;
            color: #0066cc;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            border: 1px solid #b3d9ff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }
        
        /* Stats */
        .stats-item {
            display: inline-flex;
            align-items: center;
            margin-right: 15px;
            font-size: 0.9rem;
            color: var(--secondary);
        }
        
        .stats-item i {
            margin-right: 4px;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 50rem;
        }
        
        .badge-active {
            background-color: rgba(46, 204, 113, 0.15);
            color: var(--success);
        }
        
        .badge-inactive {
            background-color: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }
        
        /* Stat Cards */
        .stat-card {
            display: flex;
            align-items: center;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.25rem;
            height: 100%;
            border-left: 4px solid var(--primary);
            position: relative;
        }
        
        .stat-card .card-body {
            padding: 0;
            position: relative;
            z-index: 1;
        }
        
        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
        }
        
        .stats-content {
            flex: 1;
        }
        
        .stats-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            line-height: 1.2;
        }
        
        .stats-label {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-bottom: 0;
        }
        
        .stats-primary {
            border-left-color: var(--primary);
        }
        
        .stats-primary .stats-icon {
            background-color: rgba(67, 97, 238, 0.15);
            color: var(--primary);
        }
        
        .stats-success {
            border-left-color: var(--success);
        }
        
        .stats-success .stats-icon {
            background-color: rgba(46, 204, 113, 0.15);
            color: var(--success);
        }
        
        .stats-info {
            border-left-color: var(--info);
        }
        
        .stats-info .stats-icon {
            background-color: rgba(52, 152, 219, 0.15);
            color: var(--info);
        }
        
        .stats-warning {
            border-left-color: var(--warning);
        }
        
        .stats-warning .stats-icon {
            background-color: rgba(243, 156, 18, 0.15);
            color: var(--warning);
        }
        
        /* Filter Card */
        .filter-card {
            border-radius: 10px;
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
        }
        
        .filters-wrapper {
            background-color: #fff;
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
        }
        
        .form-control, .form-select {
            border-color: #e2e8f0;
            border-radius: 5px;
            padding: 0.65rem 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }
        
        .search-input {
            border-radius: 50px;
            padding-left: 40px;
            background-color: #fff;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }
        
        .empty-state h4 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }
        
        /* Pagination */
        .pagination .page-link {
            color: var(--primary);
            padding: 0.5rem 0.75rem;
            border-radius: 5px;
            margin: 0 0.2rem;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        /* Alerts */
        .alert-modern {
            border-radius: 10px;
            border-left-width: 4px;
            padding: 1rem 1.25rem;
        }
        
        .alert-success {
            border-left-color: var(--success);
            background-color: rgba(46, 204, 113, 0.1);
            color: #2c7a54;
        }
        
        .alert-danger {
            border-left-color: var(--danger);
            background-color: rgba(231, 76, 60, 0.1);
            color: #a94442;
        }
        
        /* Button styles */
        .btn {
            border-radius: 5px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
        }
        
        .btn-outline-secondary {
            color: #6c757d;
            border-color: #e2e8f0;
        }
        
        .btn-outline-secondary:hover {
            background-color: #f8fafc;
            color: #4b5563;
            border-color: #d1d5db;
        }
        
        /* Action buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        
        /* Creation date in job card */
        .creation-date {
            font-size: 0.8rem;
            color: #9ca3af;
            margin-top: 1rem;
            display: flex;
            align-items: center;
        }
        
        .creation-date i {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="page-title">İş İlanları</h1>
                    <p class="page-subtitle">Tüm iş pozisyonlarını yönetin ve takip edin</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="create-job.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Yeni İlan Oluştur
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-modern alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-modern alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filtreler -->
        <div class="filters-wrapper mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Durum</label>
                    <select name="status" class="form-select">
                        <option value="">Tümü</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Pasif</option>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label">Arama</label>
                    <div class="position-relative">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" name="search" class="form-control search-input" 
                               placeholder="İlan başlığı, açıklama veya lokasyon..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>Filtrele
                    </button>
                </div>
            </form>
        </div>

        <!-- İstatistikler -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="stat-card stats-primary">
                    <div class="stats-icon">
                        <i class="bi bi-briefcase"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-value"><?= $total_records ?></div>
                        <div class="stats-label">Toplam İlan</div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="stat-card stats-success">
                    <div class="stats-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-value"><?= $active_count ?></div>
                        <div class="stats-label">Aktif İlanlar</div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="stat-card stats-info">
                    <div class="stats-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-value"><?= $application_count ?></div>
                        <div class="stats-label">Toplam Başvuru</div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card stats-warning">
                    <div class="stats-icon">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-value"><?= $inactive_count ?></div>
                        <div class="stats-label">Pasif İlanlar</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- İş İlanları Listesi -->
        <h5 class="mb-3">
            <i class="bi bi-list-ul me-2"></i>
            Tüm İş İlanları
            <span class="text-muted fs-6">(<?= $total_records ?> ilan)</span>
        </h5>
        
        <div class="row">
            <?php if (empty($jobs)): ?>
                <div class="col-12">
                    <div class="card empty-state">
                        <i class="bi bi-briefcase"></i>
                        <h4>Henüz İş İlanı Bulunmuyor</h4>
                        <p>İlk iş ilanınızı oluşturmak için aşağıdaki butona tıklayın.</p>
                        <a href="create-job.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> İlk İlanı Oluştur
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card job-card h-100 <?= $job['status'] ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title"><?= htmlspecialchars($job['title']) ?></h5>
                                    <span class="status-badge badge-<?= $job['status'] ?>">
                                        <?= $job['status'] === 'active' ? 'Aktif' : 'Pasif' ?>
                                    </span>
                                </div>
                                
                                <div class="location">
                                    <i class="bi bi-geo-alt"></i>
                                    <?= htmlspecialchars($job['location']) ?>
                                </div>
                                
                                <div class="job-description">
                                    <?= htmlspecialchars(substr(strip_tags($job['description']), 0, 120)) ?>...
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="stats-item">
                                        <i class="bi bi-people-fill text-primary"></i>
                                        <?= $job['application_count'] ?> başvuru
                                    </div>
                                    <div class="stats-item">
                                        <i class="bi bi-question-circle-fill text-info"></i>
                                        <?= $job['question_count'] ?> soru
                                    </div>
                                </div>
                                
                                <?php if (!empty($job['templates'])): ?>
                                    <div class="template-tags">
                                        <?php foreach ($job['templates'] as $template): ?>
                                            <span class="template-tag" title="<?= htmlspecialchars($template['template_name']) ?>">
                                                <?= htmlspecialchars($template['template_name']) ?> 
                                                (<?= $template['question_count'] ?>)
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="creation-date">
                                    <i class="bi bi-calendar3"></i>
                                    <?= date('d.m.Y H:i', strtotime($job['created_at'])) ?>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <div class="btn-group w-100" role="group">
                                    <a href="view-job.php?id=<?= $job['id'] ?>" 
                                       class="btn btn-outline-primary btn-sm btn-action">
                                        <i class="bi bi-eye"></i> Görüntüle
                                    </a>
                                    <a href="edit-job.php?id=<?= $job['id'] ?>" 
                                       class="btn btn-outline-secondary btn-sm btn-action">
                                        <i class="bi bi-pencil"></i> Düzenle
                                    </a>
                                    <a href="view-applications.php?job_id=<?= $job['id'] ?>" 
                                       class="btn btn-outline-info btn-sm btn-action">
                                        <i class="bi bi-people"></i> Başvurular
                                    </a>
                                </div>
                                
                                <div class="btn-group w-100 mt-2" role="group">
                                    <form method="post" class="d-inline flex-fill">
                                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $job['status'] === 'active' ? 'inactive' : 'active' ?>">
                                        <button type="submit" name="toggle_status" 
                                                class="btn btn-outline-<?= $job['status'] === 'active' ? 'warning' : 'success' ?> btn-sm w-100 btn-action">
                                            <i class="bi bi-<?= $job['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                            <?= $job['status'] === 'active' ? 'Pasifleştir' : 'Aktifleştir' ?>
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-action" 
                                            onclick="confirmDelete(<?= $job['id'] ?>, '<?= htmlspecialchars($job['title']) ?>')">
                                        <i class="bi bi-trash"></i> Sil
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Sayfalama -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Sayfa navigasyonu" class="mt-4 mb-5">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
        
        <!-- Oturum Bilgisi -->
        <div class="text-center text-muted small mb-4">
            <p>
                <i class="bi bi-info-circle me-1"></i>
                Son oturum: <?= date('Y-m-d H:i:s') ?> | Kullanıcı: <?= htmlspecialchars($_SESSION['admin_id']) ?>
            </p>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>İş İlanını Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bu işlem geri alınamaz. İş ilanı silindiğinde:</p>
                    <ul>
                        <li>Tüm başvurular silinecek</li>
                        <li>İlana ait sorular silinecek</li>
                        <li>İlan artık görüntülenemeyecek</li>
                    </ul>
                    <p><strong id="deleteJobTitle"></strong> ilanını silmek istediğinizden emin misiniz?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="job_id" id="deleteJobId">
                        <button type="submit" name="delete_job" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i> Evet, Sil
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(jobId, jobTitle) {
            document.getElementById('deleteJobId').value = jobId;
            document.getElementById('deleteJobTitle').textContent = jobTitle;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>