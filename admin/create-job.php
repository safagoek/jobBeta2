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

// Form gönderildiğinde iş ilanını oluştur
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

            // İş ilanını ekle
            $stmt = $db->prepare("INSERT INTO jobs (title, description, location, status) VALUES (:title, :description, :location, :status)");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                $job_id = $db->lastInsertId();
                
                // Seçilen şablonlar varsa şablon konfigürasyonlarını kaydet (soruları değil!)
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
                
                $db->commit();
                $success_message = "İş ilanı başarıyla oluşturuldu.";
                
                // İş ilan listeleme sayfasına yönlendir
                header("Location: manage-jobs.php?success=created");
                exit;
            } else {
                throw new Exception("İlan oluşturulurken bir hata oluştu.");
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Hata: " . $e->getMessage();
        }
    }
}

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
    <title>Yeni İş İlanı Oluştur | İş Başvuru Sistemi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary: #4361ee;
            --primary-hover: #3a56d4;
            --primary-light: #ebefff;
            --secondary: #747f8d;
            --success: #2ecc71;
            --success-light: #e3fcf7;
            --info: #3498db;
            --warning: #f39c12;
            --warning-light: #fff8eb;
            --danger: #e74c3c;
            --danger-light: #ffedf2;
            --light: #f5f7fb;
            --dark: #343a40;
            --body-bg: #f9fafb;
            --body-color: #333;
            --card-bg: #ffffff;
            --card-border: #eaedf1;
            --card-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
            --radius: 10px;
            --radius-sm: 5px;
            --transition-normal: all 0.2s ease-in-out;
            --text-dark: #333333;
            --text-light: #6c757d;
            --border-light: #e2e8f0;
            --bg-white: #ffffff;
            --focus-shadow: 0 0 0 3px rgba(67, 97, 238, 0.25);
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
            border-radius: var(--radius);
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
            color: var(--dark);
            margin-bottom: 8px;
        }

        .required-label::after {
            content: " *";
            color: var(--danger);
            font-weight: bold;
        }

        .form-control, .form-select {
            border-color: #e2e8f0;
            border-radius: var(--radius-sm);
            padding: 0.65rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #ffffff;
            border-width: 2px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: var(--focus-shadow);
        }

        .description-textarea {
            min-height: 200px;
            font-family: inherit;
            line-height: 1.6;
            resize: vertical;
        }

        /* Template kartları için iyileştirilmiş stiller */
        .template-card {
            border: 3px solid var(--border-light);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--bg-white);
            position: relative;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }
        
        .template-card:hover {
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.15);
            transform: translateY(-2px);
        }
        
        .template-card.selected {
            border-color: var(--primary);
            background-color: rgba(67, 97, 238, 0.15);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.2);
        }
        
        .question-count-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid rgba(67, 97, 238, 0.2);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .question-count-section[style*="display: block"] {
            opacity: 1;
        }

        /* Template container transition styling */
        #templatesContainer {
            transition: opacity 0.5s ease-in-out, max-height 0.5s ease-in-out;
            overflow: hidden;
        }
        
        #templatesContainer.visible {
            opacity: 1;
            max-height: 3000px; /* Larger value to accommodate all templates */
        }
        
        #templatesContainer.hidden {
            opacity: 0;
            max-height: 0;
        }

        .randomization-badge {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }

        /* Form elemanlarının kontrast ve görünürlüğünü artırmak için eklenen stiller */
        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid var(--primary);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            margin-top: 0.15rem;
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.3);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.25);
        }

        .form-check-label {
            cursor: pointer;
            padding-left: 5px;
            font-weight: 500;
            user-select: none;
        }

        .form-check {
            padding-left: 2rem;
            position: relative;
        }

        /* Checkbox ve label etkileşimleri için geliştirilmiş görsel efektler */
        .template-checkbox:checked + .template-title {
            color: var(--primary);
            font-weight: 700;
        }

        /* Seçilen şablonların özeti için geliştirilmiş stiller */
        .selected-summary-card {
            background: var(--success-light) !important;
            border: 2px solid var(--success) !important;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.15) !important;
        }

        .summary-item {
            background: var(--bg-white);
            border-radius: var(--radius-sm);
            padding: 12px 15px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .summary-item:hover {
            transform: translateX(3px);
            box-shadow: 0 3px 10px rgba(67, 97, 238, 0.1);
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 30px 0;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .template-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="page-title">Yeni İş İlanı Oluştur</h1>
                    <p class="page-subtitle">Sisteme yeni bir iş pozisyonu tanımla ve dinamik soru havuzu oluştur</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="manage-jobs.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>İlanlara Dön
                    </a>
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
                                       placeholder="Örn: Senior Frontend Developer">
                                <div class="form-text">
                                    <i class="bi bi-lightbulb me-1"></i>
                                    Açık ve net bir başlık kullanın. Bu başlık adayların ilk gördüğü şey olacak.
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label required-label">İş Tanımı</label>
                                <textarea class="form-control description-textarea" 
                                          name="description" 
                                          id="description" 
                                          required
                                          placeholder="İş ilanı açıklamasını buraya yazın...

Örnek içerik:
• Pozisyon hakkında genel bilgiler
• Aranan nitelikler ve deneyim  
• Sunulan fırsatlar
• Şirket hakkında bilgiler
• İş koşulları ve çalışma şekli"></textarea>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    İş tanımını, gereksinimleri, sorumlulukları ve şirket hakkında bilgileri detaylı bir şekilde ekleyin.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="location" class="form-label required-label">Çalışma Lokasyonu</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="location" 
                                       name="location" 
                                       required 
                                       placeholder="Örn: İstanbul / Ankara / Remote / Hibrit">
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
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Soru şablonu bulunamadı!</strong><br>
                                    Henüz soru şablonu oluşturulmamış. 
                                    <a href="manage-templates.php" class="alert-link">Şablonlar sayfasından</a> 
                                    yeni bir şablon oluşturarak başlayabilirsiniz.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-4">
                                    <i class="bi bi-magic me-2"></i>
                                    <strong>Dinamik Soru Sistemi:</strong> 
                                    Seçtiğiniz şablonlardan her başvuru için farklı sorular rastgele seçilecektir. 
                                    Bu sayede her aday farklı sorularla karşılaşacak ve sisteminiz daha adil olacaktır.
                                </div>
                                
                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           role="switch" 
                                           id="useTemplates" 
                                           onchange="toggleTemplateSection()">
                                    <label class="form-check-label" for="useTemplates">
                                        <strong>Dinamik soru havuzunu etkinleştir</strong>
                                    </label>
                                    <div class="form-text">
                                        Bu seçeneği işaretlerseniz, başvuru sahipleri seçtiğiniz şablonlardan rastgele sorularla karşılaşacaklardır.
                                    </div>
                                </div>
                                
                                <div id="templatesContainer" class="d-none">
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
                                    <option value="active">
                                        <i class="bi bi-check-circle"></i>
                                        Aktif - Hemen Yayınla
                                    </option>
                                    <option value="inactive">
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
                                    İlanı Oluştur
                                </button>
                                <a href="manage-jobs.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg me-2"></i>
                                    İptal Et
                                </a>
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
                                        <strong>Temiz metin:</strong> Açıklama düz metin olarak kaydedilir.
                                    </small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <small>
                                        <strong>Dinamik sistem:</strong> Her başvuru için farklı sorular gösterilir.
                                    </small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <small>
                                        <strong>Esnek yapı:</strong> Şablon seçmeden de ilan oluşturabilirsiniz.
                                    </small>
                                </div>
                                <div class="list-group-item border-0 px-0">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <small>
                                        <strong>Sonradan düzenleme:</strong> İlan oluşturduktan sonra da şablon ayarlarını değiştirebilirsiniz.
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
    <script>
        // Form gönderildiğinde form kontrolü yap
        document.querySelector('#jobForm').addEventListener('submit', function(e) {
            const title = document.querySelector('#title').value.trim();
            const description = document.querySelector('#description').value.trim();
            const location = document.querySelector('#location').value.trim();
            
            // Zorunlu alan kontrolü
            if (!title || !description || !location) {
                e.preventDefault();
                alert('Lütfen tüm zorunlu alanları doldurun.');
                return false;
            }
            
            // Açıklama minimum uzunluk kontrolü
            if (description.length < 10) {
                e.preventDefault();
                alert('İş tanımı en az 10 karakter olmalıdır.');
                document.querySelector('#description').focus();
                return false;
            }
            
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
        });
        
        // Şablon bölümünü aç/kapat
        function toggleTemplateSection() {
            const useTemplates = document.getElementById('useTemplates');
            const container = document.getElementById('templatesContainer');
            
            if (useTemplates.checked) {
                // Remove d-none class first
                container.classList.remove('d-none');
                
                // Set initial state
                container.classList.remove('visible');
                container.classList.add('hidden');
                
                // Force reflow to ensure transition works
                void container.offsetWidth;
                
                // Apply visible state
                container.classList.remove('hidden');
                container.classList.add('visible');
            } else {
                // Apply hidden state with transition
                container.classList.remove('visible');
                container.classList.add('hidden');
                
                // After transition completes, add d-none class
                setTimeout(() => {
                    container.classList.add('d-none');
                    
                    // Tüm şablonları temizle
                    document.querySelectorAll('.template-checkbox').forEach(checkbox => {
                        checkbox.checked = false;
                        toggleTemplateCard(checkbox);
                    });
                }, 500);
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
                
                // Animasyon efekti için highlight ekle
                card.style.transition = 'all 0.3s ease';
                card.style.backgroundColor = 'rgba(67, 97, 238, 0.25)';
                setTimeout(() => {
                    card.style.backgroundColor = 'rgba(67, 97, 238, 0.15)';
                }, 300);
                
                // Count section için fade-in efekti
                setTimeout(() => {
                    countSection.style.opacity = '1';
                }, 100);
            } else {
                card.classList.remove('selected');
                countSection.style.opacity = '0';
                
                // Count section fade-out animasyonu tamamlandıktan sonra gizle
                setTimeout(() => {
                    countSection.style.display = 'none';
                }, 300);
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

        // Sayfa yüklendiğinde ilk input'a focus ver
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.getElementById('title').focus();
            }, 100);
        });

        // Textarea'nın otomatik boyutlandırması
        const textarea = document.getElementById('description');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</body>
</html>