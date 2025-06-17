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

// İş ilanı ID'si kontrolü
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($job_id <= 0) {
    header('Location: manage-jobs.php');
    exit;
}

// İş ilanı verisini al
$stmt = $db->prepare("SELECT * FROM jobs WHERE id = :job_id");
$stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
$stmt->execute();
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: manage-jobs.php');
    exit;
}

// İlana ait mevcut şablonları al
$stmt = $db->prepare("
    SELECT jtc.template_id, jtc.question_count, qt.template_name 
    FROM job_template_configs jtc
    JOIN question_templates qt ON jtc.template_id = qt.id
    WHERE jtc.job_id = :job_id
");
$stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
$stmt->execute();
$current_templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Form gönderildiğinde iş ilanını güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $status = $_POST['status'];
    $selected_templates = !empty($_POST['selected_templates']) ? $_POST['selected_templates'] : [];

    // Basit validasyon
    if (empty($title) || empty($description) || empty($location)) {
        $error_message = "Lütfen zorunlu alanları doldurun.";
    } else {
        try {
            $db->beginTransaction();

            // İş ilanını güncelle
            $stmt = $db->prepare("UPDATE jobs SET title = :title, description = :description, location = :location, status = :status WHERE id = :job_id");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':job_id', $job_id);
            
            if ($stmt->execute()) {
                // Eğer şablon değişikliği yapılacaksa, mevcut şablonları sil
                if (isset($_POST['update_templates']) && $_POST['update_templates'] == '1') {
                    $delete_stmt = $db->prepare("DELETE FROM job_template_configs WHERE job_id = :job_id");
                    $delete_stmt->bindParam(':job_id', $job_id);
                    $delete_stmt->execute();
                    
                    // Seçilen şablonlar varsa şablon konfigürasyonlarını kaydet
                    if (!empty($selected_templates)) {
                        foreach ($selected_templates as $template_data) {
                            $template_id = $template_data['id'];
                            $question_count = (int)$template_data['count'];
                            
                            if ($question_count > 0) {
                                // Şablon konfigürasyonunu kaydet
                                $config_stmt = $db->prepare("
                                    INSERT INTO job_template_configs (job_id, template_id, question_count) 
                                    VALUES (:job_id, :template_id, :question_count)
                                ");
                                $config_stmt->bindParam(':job_id', $job_id);
                                $config_stmt->bindParam(':template_id', $template_id);
                                $config_stmt->bindParam(':question_count', $question_count);
                                $config_stmt->execute();
                            }
                        }
                    }
                }
                
                $db->commit();
                $success_message = "İş ilanı başarıyla güncellendi.";
                
                // İş ilanı bilgilerini yeniden yükle
                $stmt = $db->prepare("SELECT * FROM jobs WHERE id = :job_id");
                $stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
                $stmt->execute();
                $job = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Güncel şablonları yeniden yükle
                $stmt = $db->prepare("
                    SELECT jtc.template_id, jtc.question_count, qt.template_name 
                    FROM job_template_configs jtc
                    JOIN question_templates qt ON jtc.template_id = qt.id
                    WHERE jtc.job_id = :job_id
                ");
                $stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
                $stmt->execute();
                $current_templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } else {
                throw new Exception("İlan güncellenirken bir hata oluştu.");
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Hata: " . $e->getMessage();
        }
    }
}

// İlana ait soru sayısını al
$stmt = $db->prepare("SELECT COUNT(*) FROM questions WHERE job_id = :job_id");
$stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
$stmt->execute();
$question_count = $stmt->fetchColumn();

// İlana ait başvuru sayısını al
$stmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE job_id = :job_id");
$stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
$stmt->execute();
$application_count = $stmt->fetchColumn();

// Şablonları getir ve her şablondaki soru sayısını da al
$stmt = $db->query("
    SELECT 
        qt.id, 
        qt.template_name, 
        qt.description,
        COUNT(tq.id) as total_questions
    FROM question_templates qt
    LEFT JOIN template_questions tq ON qt.id = tq.template_id
    GROUP BY qt.id, qt.template_name, qt.description
    ORDER BY qt.template_name
");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İş İlanı Düzenle | İş Başvuru Sistemi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
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
        }
        
        body {
            background-color: var(--body-bg);
            color: var(--body-color);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 0.9rem;
        }
        
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
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .required-label::after {
            content: ' *';
            color: var(--danger);
        }
        
        .form-control, .form-select {
            padding: 0.65rem 1rem;
            border-color: #e2e8f0;
            border-radius: 5px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }
        
        .alert-info {
            border-left-color: var(--info);
            background-color: rgba(52, 152, 219, 0.1);
            color: #2471a3;
        }
        
        .alert-warning {
            border-left-color: var(--warning);
            background-color: rgba(243, 156, 18, 0.1);
            color: #9a7a40;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 0.65rem 1.25rem;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        
        .btn-outline-secondary {
            border-color: #e2e8f0;
            color: var(--secondary);
        }
        
        .btn-outline-secondary:hover {
            background-color: #f8fafc;
            color: var(--dark);
        }
        
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
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
        
        .form-text {
            color: var(--secondary);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .template-card {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        
        .template-card.selected {
            background-color: #ebf5ff;
            border-color: #93c5fd;
        }
        
        .template-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .template-desc {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }
        
        .template-stats {
            font-size: 0.8rem;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .question-count-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px dashed #e2e8f0;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .question-count-input {
            max-width: 200px;
        }
        
        .help-card {
            background-color: #f8fafc;
        }
        
        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 1.25rem 0;
        }
        
        .randomization-badge {
            background-color: #fff7ed;
            color: #ea580c;
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
            margin-left: 0.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .summary-item {
            padding: 0.75rem 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .ql-editor {
            min-height: 200px;
        }
        
        /* Status pill styles */
        .status-pill {
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
        
        .status-pill-active {
            background-color: #10b981;
            color: white;
        }
        
        .status-pill-inactive {
            background-color: #6b7280;
            color: white;
        }
        
        .warning-message {
            background-color: #fff7ed;
            border-left: 4px solid #ea580c;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="page-title">İş İlanını Düzenle</h1>
                    <p class="page-subtitle">
                        <?= htmlspecialchars($job['title']) ?>
                        <span class="status-pill ms-2 status-pill-<?= $job['status'] ?>">
                            <?= $job['status'] === 'active' ? 'Aktif' : 'Pasif' ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="manage-jobs.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>İlanlara Dön
                    </a>
                    <a href="view-job.php?id=<?= $job_id ?>" class="btn btn-outline-primary ms-2">
                        <i class="bi bi-eye me-1"></i>İlanı Görüntüle
                    </a>
                </div>
            </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-modern alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Başarılı!</strong> <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-modern alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Hata!</strong> <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($application_count > 0): ?>
            <div class="alert alert-warning alert-modern mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Dikkat:</strong> Bu ilana <?= $application_count ?> başvuru bulunmaktadır. 
                Yapacağınız değişiklikler mevcut başvuruları etkilemeyecektir, ancak yeni başvurular için geçerli olacaktır.
            </div>
        <?php endif; ?>

        <form method="post" id="jobForm">
            <div class="row">
                <div class="col-lg-8">
                    <!-- İş İlanı Bilgileri Kartı -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-info-circle me-2 text-primary"></i>
                                İş İlanı Bilgileri
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <label for="title" class="form-label required-label">İlan Başlığı</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="title" 
                                       name="title" 
                                       required
                                       placeholder="Örn: Senior Frontend Developer"
                                       value="<?= htmlspecialchars($job['title']) ?>">
                                <div class="form-text">
                                    <i class="bi bi-lightbulb me-1"></i>
                                    Açık ve net bir başlık kullanın. Bu başlık adayların ilk gördüğü şey olacak.
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label required-label">İş Tanımı</label>
                                <textarea 
                                    class="form-control" 
                                    id="description" 
                                    name="description" 
                                    placeholder="İş tanımını, gereksinimleri, sorumlulukları ve şirket hakkında bilgileri girin."
                                    required
                                    rows="6"
                                    style="resize: vertical; min-height: 150px;"
                                ><?= htmlspecialchars($job['description']) ?></textarea>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    İş tanımını, gereksinimleri, sorumlulukları ve şirket hakkında bilgileri ekleyin.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="location" class="form-label required-label">Çalışma Lokasyonu</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="location" 
                                       name="location" 
                                       required 
                                       placeholder="Örn: İstanbul / Ankara / Remote / Hibrit"
                                       value="<?= htmlspecialchars($job['location']) ?>">
                                <div class="form-text">
                                    <i class="bi bi-geo-alt me-1"></i>
                                    Şehir, ilçe veya uzaktan çalışma seçeneklerini belirtin.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dinamik Soru Şablonu Seçimi -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-shuffle me-2 text-primary"></i>
                                Dinamik Soru Havuzu Ayarları
                                <span class="randomization-badge">
                                    <i class="bi bi-stars me-1"></i>YENİ
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($templates)): ?>
                                <div class="alert alert-info alert-modern">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Soru şablonu bulunamadı!</strong><br>
                                    Henüz soru şablonu oluşturulmamış. 
                                    <a href="manage-templates.php" class="alert-link">Şablonlar sayfasından</a> 
                                    yeni bir şablon oluşturarak başlayabilirsiniz.
                                </div>
                            <?php else: ?>
                                <?php if (!empty($current_templates)): ?>
                                    <div class="alert alert-info alert-modern mb-4">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>Mevcut Şablonlar:</strong> Bu iş ilanı aşağıdaki şablonları kullanıyor:
                                        <ul class="mb-0 mt-2">
                                            <?php foreach ($current_templates as $template): ?>
                                                <li>
                                                    <strong><?= htmlspecialchars($template['template_name']) ?></strong> 
                                                    (<?= $template['question_count'] ?> soru)
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           role="switch" 
                                           id="updateTemplates" 
                                           name="update_templates"
                                           value="1"
                                           onchange="toggleTemplateSection()">
                                    <label class="form-check-label" for="updateTemplates">
                                        <strong>Soru şablonlarını değiştir</strong>
                                    </label>
                                    <div class="form-text">
                                        Bu seçeneği işaretlerseniz, mevcut şablon ayarları değiştirilecek ve yeni seçimleriniz kaydedilecektir.
                                    </div>
                                </div>
                                
                                <div id="templatesContainer" class="d-none">
                                    <div class="alert alert-warning alert-modern">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <strong>Dikkat:</strong> Şablon ayarlarını değiştirirseniz, mevcut ayarlar silinecek ve yeni seçimleriniz geçerli olacaktır.
                                    </div>
                                
                                    <h6 class="mb-3">
                                        <i class="bi bi-collection me-2"></i>
                                        Mevcut Soru Şablonları
                                    </h6>
                                    
                                    <?php foreach ($templates as $template): ?>
                                        <div class="template-card" data-id="<?= $template['id'] ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input template-checkbox" 
                                                               type="checkbox" 
                                                               id="template_<?= $template['id'] ?>" 
                                                               value="<?= $template['id'] ?>"
                                                               onchange="toggleTemplateCard(this)">
                                                        <label class="form-check-label template-title" 
                                                               for="template_<?= $template['id'] ?>">
                                                            <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                                                            <?= htmlspecialchars($template['template_name']) ?>
                                                        </label>
                                                    </div>
                                                    <p class="template-desc">
                                                        <?= htmlspecialchars($template['description'] ?: 'Açıklama bulunmamaktadır.') ?>
                                                    </p>
                                                    <div class="template-stats">
                                                        <i class="bi bi-question-circle-fill"></i>
                                                        <span><?= $template['total_questions'] ?> soru mevcut</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="question-count-section" style="display: none;">
                                                <div class="row align-items-center">
                                                    <div class="col-md-6">
                                                        <label class="form-label mb-1">
                                                            <i class="bi bi-hash me-1"></i>
                                                            Bu şablondan kaç soru seçilsin?
                                                        </label>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="input-group question-count-input">
                                                            <input type="number" 
                                                                   class="form-control" 
                                                                   min="1" 
                                                                   max="<?= $template['total_questions'] ?>"
                                                                   value="<?= min(3, $template['total_questions']) ?>"
                                                                   id="count_<?= $template['id'] ?>"
                                                                   onchange="updateSummary()">
                                                            <span class="input-group-text">soru</span>
                                                        </div>
                                                        <div class="form-text">
                                                            En fazla <?= $template['total_questions'] ?> soru seçilebilir
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Yayınlama Seçenekleri -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-gear me-2 text-primary"></i>
                                Yayınlama Seçenekleri
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <label for="status" class="form-label">İlan Durumu</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?= $job['status'] === 'active' ? 'selected' : '' ?>>
                                        <i class="bi bi-check-circle"></i>
                                        Aktif - Yayında
                                    </option>
                                    <option value="inactive" <?= $job['status'] === 'inactive' ? 'selected' : '' ?>>
                                        <i class="bi bi-pause-circle"></i>
                                        Pasif - Taslak Olarak Kaydet
                                    </option>
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Aktif ilanlar anasayfada görüntülenir ve başvurulara açıktır.
                                </div>
                            </div>

                            <div class="divider"></div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg me-2"></i>
                                    Değişiklikleri Kaydet
                                </button>
                                <a href="manage-jobs.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg me-2"></i>
                                    İptal Et
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- İlan Detay Özeti -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-info-circle me-2 text-primary"></i>
                                İlan Detayları
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <div>İlan Tarihi:</div>
                                <div class="fw-medium"><?= date('d.m.Y H:i', strtotime($job['created_at'])) ?></div>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <div>Toplam Soru:</div>
                                <div class="fw-medium">
                                    <?= $question_count ?> soru
                                    <?php if ($question_count > 0): ?>
                                        <a href="manage-job-questions.php?job_id=<?= $job_id ?>" class="ms-2 text-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <div>Toplam Başvuru:</div>
                                <div class="fw-medium">
                                    <?= $application_count ?> başvuru
                                    <?php if ($application_count > 0): ?>
                                        <a href="view-applications.php?job_id=<?= $job_id ?>" class="ms-2 text-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <div>Durum:</div>
                                <div class="fw-medium">
                                    <span class="status-pill status-pill-<?= $job['status'] ?>">
                                        <?= $job['status'] === 'active' ? 'Aktif' : 'Pasif' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seçilen Şablonlar Özeti -->
                    <div class="card selected-summary-card d-none" id="selectedTemplatesSummary">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-list-check me-2"></i>
                                Seçilen Şablonlar Özeti
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="summaryContent"></div>
                            <div class="divider"></div>
                            <div class="text-center">
                                <div class="badge bg-success fs-6" id="totalQuestions">
                                    Toplam: 0 soru
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Yardım ve İpuçları -->
                    <div class="card help-card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-question-circle me-2"></i>
                                Yardım ve İpuçları
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item border-0 px-0">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <small>
                                        <strong>Dinamik sistem:</strong> Her başvuru için farklı sorular gösterilir.
                                    </small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <small>
                                        <strong>Esnek yapı:</strong> Şablon seçmeden de ilan düzenleyebilirsiniz.
                                    </small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <small>
                                        <strong>Mevcut başvurular:</strong> Şablon değişiklikleri sadece yeni başvuruları etkiler.
                                    </small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <small>
                                        <strong>Adil değerlendirme:</strong> Rastgele soru seçimi ile tüm adaylar eşit şartlarda değerlendirilir.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script>
        // Quill zengin metin editörü
        var quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'İş ilanı açıklamasını buraya yazın...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    ['link', 'blockquote'],
                    ['clean']
                ]
            }
        });
        
        // Form gönderilirken input değerini kontrol et
        document.querySelector('#jobForm').addEventListener('submit', function(e) {
            var description = document.querySelector('#description');
            description.value = description.value.trim();
            
            // Açıklama boş mu kontrol et
            if (description.value.length === 0) {
                e.preventDefault();
                alert('Lütfen iş tanımını doldurun.');
                description.focus();
                return false;
            }
            
            // Şablon değişikliği yapılacaksa seçilen şablonları kontrol et
            const updateTemplates = document.getElementById('updateTemplates');
            if (updateTemplates && updateTemplates.checked) {
                // Seçilen şablonları form data olarak hazırla
                const selectedTemplates = getSelectedTemplates();
                
                // Mevcut hidden input'ları temizle
                const existingInputs = document.querySelectorAll('input[name^="selected_templates"]');
                existingInputs.forEach(input => input.remove());
                
                // Yeni hidden input'ları ekle
                selectedTemplates.forEach((template, index) => {
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = `selected_templates[${index}][id]`;
                    idInput.value = template.id;
                    this.appendChild(idInput);
                    
                    const countInput = document.createElement('input');
                    countInput.type = 'hidden';
                    countInput.name = `selected_templates[${index}][count]`;
                    countInput.value = template.count;
                    this.appendChild(countInput);
                });
            }
        });
        
        // Şablon bölümünü aç/kapat
        function toggleTemplateSection() {
            const updateTemplates = document.getElementById('updateTemplates');
            const container = document.getElementById('templatesContainer');
            
            if (updateTemplates.checked) {
                container.classList.remove('d-none');
                // Animasyon efekti için
                setTimeout(() => {
                    container.style.opacity = '1';
                }, 50);
            } else {
                container.classList.add('d-none');
                // Tüm şablonları temizle
                document.querySelectorAll('.template-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                    toggleTemplateCard(checkbox);
                });
            }
            
            updateSummary();
        }
        
        // Şablon kartını seç/seçimi kaldır
        function toggleTemplateCard(checkbox) {
            const card = checkbox.closest('.template-card');
            const countSection = card.querySelector('.question-count-section');
            
            if (checkbox.checked) {
                card.classList.add('selected');
                countSection.style.display = 'block';
                // Animasyon efekti
                setTimeout(() => {
                    countSection.style.opacity = '1';
                }, 100);
            } else {
                card.classList.remove('selected');
                countSection.style.display = 'none';
            }
            
            updateSummary();
        }
        
        // Seçilen şablonları al
        function getSelectedTemplates() {
            const selectedTemplates = [];
            
            document.querySelectorAll('.template-checkbox:checked').forEach(checkbox => {
                const templateId = checkbox.value;
                const countInput = document.getElementById(`count_${templateId}`);
                const count = parseInt(countInput.value) || 1;
                
                // Şablon ismini de al
                const templateName = checkbox.closest('.template-card').querySelector('.template-title').textContent.trim();
                
                selectedTemplates.push({
                    id: templateId,
                    count: count,
                    name: templateName
                });
            });
            
            return selectedTemplates;
        }
        
        // Seçilen şablonların özetini güncelle
        function updateSummary() {
            const selectedTemplates = getSelectedTemplates();
            const summaryCard = document.getElementById('selectedTemplatesSummary');
            const summaryContent = document.getElementById('summaryContent');
            const totalBadge = document.getElementById('totalQuestions');
            
            if (selectedTemplates.length > 0) {
                summaryCard.classList.remove('d-none');
                
                let html = '';
                let totalQuestions = 0;
                
                selectedTemplates.forEach(template => {
                    const cleanName = template.name.replace(/^\s*[\w\s]*\s*/, '').trim();
                    
                    html += `
                        <div class="summary-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-file-earmark-text text-primary me-2"></i>
                                    <strong>${cleanName}</strong>
                                </div>
                                <span class="badge bg-primary">${template.count} soru</span>
                            </div>
                        </div>
                    `;
                    
                    totalQuestions += parseInt(template.count);
                });
                
                summaryContent.innerHTML = html;
                totalBadge.textContent = `Toplam: ${totalQuestions} soru`;
            } else {
                summaryCard.classList.add('d-none');
            }
        }
        
        // Soru sayısı değiştiğinde özeti güncelle
        document.querySelectorAll('input[id^="count_"]').forEach(input => {
            input.addEventListener('input', function() {
                // Maksimum değeri aşmasını engelle
                const max = parseInt(this.getAttribute('max'));
                if (parseInt(this.value) > max) {
                    this.value = max;
                }
                // Minimum değeri 1 yap
                if (parseInt(this.value) < 1) {
                    this.value = 1;
                }
                updateSummary();
            });
        });

        // Template card'a tıklayınca checkbox'ı toggle et
        document.querySelectorAll('.template-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Sadece input alanları tıklanmadığında çalışsın
                if (!e.target.matches('input')) {
                    const checkbox = this.querySelector('.template-checkbox');
                    checkbox.checked = !checkbox.checked;
                    toggleTemplateCard(checkbox);
                }
            });
        });

        // Sayfa yüklendiğinde mevcut şablonları preselect et
        document.addEventListener('DOMContentLoaded', function() {
            // Mevcut şablonlar varsa onları seçili göster
            <?php if (!empty($current_templates)): ?>
                const currentTemplates = <?= json_encode($current_templates) ?>;
                currentTemplates.forEach(template => {
                    const checkbox = document.getElementById(`template_${template.template_id}`);
                    if (checkbox) {
                        checkbox.checked = true;
                        toggleTemplateCard(checkbox);
                        
                        // Soru sayısını da güncelle
                        const countInput = document.getElementById(`count_${template.template_id}`);
                        if (countInput) {
                            countInput.value = template.question_count;
                        }
                    }
                });
                
                // Şablon özet kutusunu güncelle
                updateSummary();
            <?php endif; ?>
        });
    </script>
</body>
</html>