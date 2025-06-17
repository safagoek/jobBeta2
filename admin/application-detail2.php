<?php
session_start();
require_once '../config/db.php'; // Düzeltme: ../ eklendi

// Admin kontrolü



// URL'den uygulama ID'sini al
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Tüm adayları al (listelemek için)
$stmt = $db->prepare("
    SELECT a.*, j.title as job_title, j.location as job_location
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    ORDER BY a.created_at DESC
");
$stmt->execute();
$all_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İş ilanlarını al
$stmt = $db->prepare("SELECT id, title FROM jobs ORDER BY title");
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Eğer belirli bir aday seçilmişse detaylarını al
$application = null;
$answers = [];
$total_score = 0;
$total_questions = 0;
$mc_correct = 0;
$mc_total = 0;
$open_ended_total = 0;
$open_ended_avg_score = 0;

if ($application_id > 0) {
    // Başvuru detaylarını al
    $stmt = $db->prepare("
        SELECT a.*, j.title as job_title, j.location as job_location, j.description as job_description
        FROM applications a 
        JOIN jobs j ON a.job_id = j.id 
        WHERE a.id = ?
    ");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        die("Başvuru bulunamadı");
    }

    // Başvuru cevaplarını al - answer_feedback dahil edildi
    $stmt = $db->prepare("
        SELECT aa.*, aa.answer_feedback, tq.question_text, tq.question_type, 
               to1.option_text as selected_option, to1.is_correct as selected_is_correct,
               qt.template_name
        FROM application_answers aa 
        JOIN template_questions tq ON aa.question_id = tq.id 
        LEFT JOIN template_options to1 ON aa.option_id = to1.id 
        LEFT JOIN question_templates qt ON tq.template_id = qt.id
        WHERE aa.application_id = ? 
        ORDER BY tq.id
    ");
    $stmt->execute([$application_id]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Puan hesaplamaları
    foreach ($answers as $answer) {
        $total_questions++;
        
        if ($answer['question_type'] == 'multiple_choice') {
            $mc_total++;
            if ($answer['selected_is_correct'] == 1) {
                $mc_correct++;
                $total_score++;
            }
        } else {
            $open_ended_total++;
            $open_ended_avg_score += ($answer['answer_score'] ?? 0);
        }
    }

    if ($open_ended_total > 0) {
        $open_ended_avg_score = $open_ended_avg_score / $open_ended_total;
    }

    // Genel puanı hesapla
    $mc_percentage = $mc_total > 0 ? ($mc_correct / $mc_total) * 100 : 0;
    $overall_score = ($mc_percentage * 0.7) + ($open_ended_avg_score * 0.3);
}

// Form işlemleri
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CV puanı güncelleme
        if (isset($_POST['action']) && $_POST['action'] === 'update_cv_score') {
            $cv_score = max(0, min(100, (int)$_POST['cv_score']));
            
            $stmt = $db->prepare("UPDATE applications SET cv_score = ? WHERE id = ?");
            $stmt->execute([$cv_score, $application_id]);
            
            $application['cv_score'] = $cv_score;
            $success = "CV puanı başarıyla güncellendi.";
        }
        
        // Açık uçlu yanıt puanı güncelleme
        if (isset($_POST['action']) && $_POST['action'] === 'update_answer_score') {
            $answer_id = (int)$_POST['answer_id'];
            $answer_score = max(0, min(100, (int)$_POST['answer_score']));
            
            $stmt = $db->prepare("UPDATE application_answers SET answer_score = ? WHERE id = ?");
            $stmt->execute([$answer_score, $answer_id]);
            
            // Yanıtları yeniden yükle
            $stmt = $db->prepare("
                SELECT aa.*, aa.answer_feedback, tq.question_text, tq.question_type, 
                       to1.option_text as selected_option, to1.is_correct as selected_is_correct,
                       qt.template_name
                FROM application_answers aa 
                JOIN template_questions tq ON aa.question_id = tq.id 
                LEFT JOIN template_options to1 ON aa.option_id = to1.id 
                LEFT JOIN question_templates qt ON tq.template_id = qt.id
                WHERE aa.application_id = ? 
                ORDER BY tq.id
            ");
            $stmt->execute([$application_id]);
            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $success = "Yanıt puanı başarıyla güncellendi.";
        }
        
        // Not güncelleme
        if (isset($_POST['action']) && $_POST['action'] === 'update_note') {
            $admin_note = $_POST['admin_note'];
            
            $stmt = $db->prepare("UPDATE applications SET admin_note = ? WHERE id = ?");
            $stmt->execute([$admin_note, $application_id]);
            
            $application['admin_note'] = $admin_note;
            $success = "Not başarıyla kaydedildi.";
        }
        
        // Durum güncelleme
        if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
            $new_status = $_POST['status'];
            
            $stmt = $db->prepare("UPDATE applications SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $application_id]);
            
            $application['status'] = $new_status;
            $success = "Başvuru durumu güncellendi.";
        }
        
        // CV Feedback güncelleme
        if (isset($_POST['action']) && $_POST['action'] === 'update_cv_feedback') {
            $cv_feedback = $_POST['cv_feedback'];
            
            $stmt = $db->prepare("UPDATE applications SET cv_feedback = ? WHERE id = ?");
            $stmt->execute([$cv_feedback, $application_id]);
            
            $application['cv_feedback'] = $cv_feedback;
            $success = "CV geri bildirimi başarıyla kaydedildi.";
        }
        
    } catch (Exception $e) {
        $error = "İşlem sırasında hata oluştu: " . $e->getMessage();
    }
}

// Aktif istatistikleri hesapla
$new_applications = 0;
$reviewed_applications = 0;
$avg_cv_score = 0;
$total_cv_scores = 0;

foreach ($all_applications as $app) {
    if ($app['status'] == 'pending') {
        $new_applications++;
    } else {
        $reviewed_applications++;
    }
    
    if ($app['cv_score'] > 0) {
        $avg_cv_score += $app['cv_score'];
        $total_cv_scores++;
    }
}

if ($total_cv_scores > 0) {
    $avg_cv_score = round($avg_cv_score / $total_cv_scores);
}

$total_applications = count($all_applications);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $application_id > 0 ? "Aday Detayı" : "Aday Değerlendirme"; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-subtitle {
            color: var(--secondary);
            font-weight: 400;
            margin-bottom: 0;
        }
        
        .back-btn {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
            transition: var(--transition-normal);
        }
        
        .back-btn:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }
        
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid var(--card-border);
            padding: 1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            margin-bottom: 0;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-row {
            margin-bottom: 0.75rem;
            display: flex;
        }
        
        .info-label {
            width: 100px;
            color: var(--secondary);
            font-weight: 500;
        }
        
        .info-value {
            flex: 1;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 600;
            border-radius: 50px;
        }
        
        .badge-pending {
            background-color: rgba(246, 153, 63, 0.15);
            color: #f6993f;
        }
        
        .badge-completed {
            background-color: rgba(46, 204, 113, 0.15);
            color: var(--success);
        }
        
        .badge-reviewed {
            background-color: rgba(52, 152, 219, 0.15);
            color: var(--info);
        }
        
        .badge-rejected {
            background-color: rgba(231, 76, 60, 0.15);
            color: var(--danger);
        }
        
        .badge-accepted {
            background-color: rgba(155, 89, 182, 0.15);
            color: #9b59b6;
        }
        
        .score-badge {
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 auto;
        }
        
        .score-good {
            background-color: rgba(46, 204, 113, 0.15);
            color: var(--success);
            border: 2px solid var(--success);
        }
        
        .score-medium {
            background-color: rgba(246, 153, 63, 0.15);
            color: var(--warning);
            border: 2px solid var(--warning);
        }
        
        .score-poor {
            background-color: rgba(231, 76, 60, 0.15);
            color: var(--danger);
            border: 2px solid var(--danger);
        }
        
        .progress-bar-container {
            margin-bottom: 1rem;
        }
        
        .progress-bar-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .progress-bar-label .label {
            font-weight: 500;
            color: var(--secondary);
        }
        
        .progress-bar-label .value {
            font-weight: 600;
        }
        
        .progress {
            height: 8px;
            border-radius: 50px;
            background-color: #e9ecef;
        }
        
        .progress-bar {
            border-radius: 50px;
        }
        
        .question-card {
            border: 1px solid var(--card-border);
            border-radius: 10px;
            margin-bottom: 1rem;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
        
        .question-header {
            background-color: #f8fafc;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--card-border);
        }
        
        .question-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .question-meta {
            font-size: 0.8rem;
            color: var(--secondary);
        }
        
        .answer-content {
            padding: 1rem;
        }
        
        .answer-text {
            background-color: #f8fafc;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .correct-answer {
            border-left: 4px solid var(--success);
        }
        
        .wrong-answer {
            border-left: 4px solid var(--danger);
        }
        
        .search-input {
            padding: 0.75rem 1rem;
            border: 1px solid var(--card-border);
            border-radius: 0.5rem;
            width: 100%;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .stat-card {
            display: flex;
            flex-direction: column;
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            height: 100%;
            border-top: 4px solid var(--primary);
        }
        
        .stat-primary {
            border-top-color: var(--primary);
        }
        
        .stat-info {
            border-top-color: var(--info);
        }
        
        .stat-success {
            border-top-color: var(--success);
        }
        
        .stat-warning {
            border-top-color: var(--warning);
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-desc {
            font-size: 0.85rem;
            color: var(--secondary);
        }
        
        .stat-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.25rem;
            opacity: 0.15;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .date-filter-badge {
            display: inline-flex;
            align-items: center;
            background-color: var(--light);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            color: var(--secondary);
            font-weight: 500;
        }
        
        /* CV Feedback card */
        .cv-feedback-card {
            background-color: var(--light);
            border-radius: 10px;
            padding: 1.25rem;
            margin-top: 1rem;
            border-left: 4px solid var(--primary);
        }
        
        .cv-feedback-header {
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .stats-box {
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
            margin-top: 1rem;
            border: 1px solid var(--card-border);
        }
        
        .stats-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .stats-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed var(--card-border);
        }
        
        .stats-item:last-child {
            border-bottom: none;
        }
        
        .stats-label {
            color: var(--secondary);
        }
        
        .stats-value {
            font-weight: 600;
        }
        
        .value-positive {
            color: var(--success);
        }
        
        .value-negative {
            color: var(--danger);
        }
        
        /* Notes card - smaller style */
        .notes-card {
            max-height: 300px;
            overflow-y: auto;
        }
        
        /* CV feedback - expanded style */
        .cv-feedback-expanded {
            min-height: 200px;
        }
        
        /* AI Feedback styles */
        .ai-feedback-section {
            background-color: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.3);
            border-radius: 10px;
            padding: 1.25rem;
            margin-top: 1rem;
        }
        
        .ai-feedback-header {
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--info);
        }
        
        .ai-feedback-content {
            color: var(--body-color);
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        .no-ai-feedback {
            color: var(--secondary);
            font-style: italic;
            text-align: center;
            padding: 1rem;
        }
        
        .btn {
            border-radius: 5px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: var(--transition-normal);
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
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            font-weight: 600;
            color: var(--secondary);
            border-top: none;
            padding: 1rem 0.75rem;
        }
        
        .table td {
            vertical-align: middle;
            padding: 0.75rem;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <?php if ($application_id > 0): ?>
                <a href="application-detail2.php" class="back-btn">
                    <i class="bi bi-arrow-left me-2"></i>Başvuru Listesine Dön
                </a>
                <h1 class="page-title">
                    <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                    <span class="status-badge badge-<?php echo $application['status']; ?>">
                        <?php 
                        echo match($application['status']) {
                            'pending' => 'Beklemede',
                            'completed' => 'Tamamlandı',
                            'reviewed' => 'İncelendi',
                            'rejected' => 'Reddedildi',
                            'accepted' => 'Kabul Edildi',
                            default => ucfirst($application['status'])
                        };
                        ?>
                    </span>
                </h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($application['job_title']); ?> pozisyonu için başvuru</p>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">Aday Değerlendirme</h1>
                        <p class="page-subtitle">Tüm başvuruları yönetin ve değerlendirin</p>
                    </div>
                    <div>
                        <span class="date-filter-badge">
                            <i class="bi bi-calendar me-2"></i>Tüm başvurular
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($application_id > 0): ?>
            <!-- ADAY DETAY GÖRÜNÜMÜ -->
            <!-- Kişisel Bilgiler ve Not -->
            <div class="row">
                <!-- Kişisel Bilgiler -->
                <div class="col-lg-9 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title"><i class="bi bi-person me-2"></i>Kişisel Bilgiler</span>
                            <div>
                                <a href="../<?php echo htmlspecialchars($application['cv_path']); ?>" class="btn btn-primary btn-sm me-2" target="_blank">
                                    <i class="bi bi-file-earmark-pdf me-1"></i>CV İndir
                                </a>
                                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#statusModal">
                                    <i class="bi bi-arrow-repeat me-1"></i>Durum Değiştir
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <div class="info-label">E-posta:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($application['email']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Telefon:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($application['phone']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Cinsiyet:</div>
                                        <div class="info-value">
                                            <?php echo match($application['gender']) {
                                                'male' => 'Erkek',
                                                'female' => 'Kadın',
                                                default => 'Belirtilmemiş'
                                            }; ?>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Yaş:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($application['age']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <div class="info-label">Şehir:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($application['city']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Eğitim:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($application['education']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Deneyim:</div>
                                        <div class="info-value"><?php echo ($application['experience'] ?? 0); ?> yıl</div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Maaş Beklentisi:</div>
                                        <div class="info-value"><?php echo number_format($application['salary_expectation'], 0, ',', '.'); ?> ₺</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- CV Score and Feedback -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="fw-semibold"><i class="bi bi-file-earmark-text me-2"></i>CV Değerlendirmesi</h5>
                                        <div>
                                            <button class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#cvScoreModal">
                                                <i class="bi bi-star me-1"></i>CV Puanla
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cvFeedbackModal">
                                                <i class="bi bi-pencil me-1"></i>Geri Bildirim Düzenle
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="stats-box">
                                                <div class="stats-title">
                                                    <i class="bi bi-bar-chart-fill me-2"></i>Test Sonuçları
                                                </div>
                                                <div class="stats-item">
                                                    <span class="stats-label">Toplam Soru:</span>
                                                    <span class="stats-value"><?php echo $total_questions; ?></span>
                                                </div>
                                                <div class="stats-item">
                                                    <span class="stats-label">Çoktan Seçmeli:</span>
                                                    <span class="stats-value"><?php echo $mc_total; ?></span>
                                                </div>
                                                <div class="stats-item">
                                                    <span class="stats-label">Doğru Cevap:</span>
                                                    <span class="stats-value value-positive"><?php echo $mc_correct; ?></span>
                                                </div>
                                                <div class="stats-item">
                                                    <span class="stats-label">Yanlış Cevap:</span>
                                                    <span class="stats-value value-negative"><?php echo ($mc_total - $mc_correct); ?></span>
                                                </div>
                                                <div class="stats-item">
                                                    <span class="stats-label">Açık Uçlu Soru:</span>
                                                    <span class="stats-value"><?php echo $open_ended_total; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="cv-feedback-card cv-feedback-expanded">
                                                <div class="cv-feedback-header">
                                                    <i class="bi bi-chat-quote me-2"></i>CV Geri Bildirimi
                                                </div>
                                                <div class="progress-bar-container mb-3">
                                                    <div class="progress-bar-label">
                                                        <span class="label">CV Puanı</span>
                                                        <span class="value"><?php echo $application['cv_score'] ?? 0; ?>%</span>
                                                    </div>
                                                    <div class="progress">
                                                        <div class="progress-bar bg-info" style="width: <?php echo $application['cv_score'] ?? 0; ?>%"></div>
                                                    </div>
                                                </div>
                                                <div style="min-height: 150px;">
                                                    <?php if (!empty($application['cv_feedback'])): ?>
                                                        <?php echo nl2br(htmlspecialchars($application['cv_feedback'])); ?>
                                                    <?php else: ?>
                                                        <p class="text-muted text-center">Henüz CV geri bildirimi eklenmemiş</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notlar - küçültülmüş -->
                <div class="col-lg-3 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title"><i class="bi bi-sticky-note me-2"></i>Notlar</span>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#noteModal">
                                <i class="bi bi-pencil me-1"></i>Düzenle
                            </button>
                        </div>
                        <div class="card-body notes-card">
                            <?php if (!empty($application['admin_note'])): ?>
                                <?php echo nl2br(htmlspecialchars($application['admin_note'])); ?>
                            <?php else: ?>
                                <p class="text-muted text-center">Henüz not eklenmemiş</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Test Cevapları -->
            <div class="card mb-4">
                <div class="card-header">
                    <span class="card-title"><i class="bi bi-list-check me-2"></i>Test Cevapları</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($answers)): ?>
                        <!-- Çoktan Seçmeli Sorular -->
                        <div class="mb-4">
                            <h5 class="fw-semibold mb-3">Çoktan Seçmeli Sorular</h5>
                            
                            <?php
                            $mc_counter = 1;
                            foreach ($answers as $answer):
                                if ($answer['question_type'] == 'multiple_choice'):
                                    $is_correct = $answer['selected_is_correct'] == 1;
                            ?>
                                <div class="question-card <?php echo $is_correct ? 'correct-answer' : 'wrong-answer'; ?>">
                                    <div class="question-header">
                                        <div class="question-title">
                                            <?php echo $mc_counter++; ?>. <?php echo htmlspecialchars($answer['question_text']); ?>
                                        </div>
                                        <div class="question-meta">
                                            Şablon: <?php echo htmlspecialchars($answer['template_name'] ?? 'Bilinmiyor'); ?>
                                        </div>
                                    </div>
                                    <div class="answer-content">
                                        <div class="d-flex align-items-center">
                                            <span class="me-2">
                                                <?php echo $is_correct ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>'; ?>
                                            </span>
                                            <span><?php echo htmlspecialchars($answer['selected_option'] ?? 'Yanıtlanmamış'); ?></span>
                                            <span class="ms-auto status-badge <?php echo $is_correct ? 'badge-completed' : 'badge-pending'; ?>">
                                                <?php echo $is_correct ? 'Doğru' : 'Yanlış'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                        
                        <!-- Açık Uçlu Sorular -->
                        <?php if ($open_ended_total > 0): ?>
                        <div>
                            <h5 class="fw-semibold mb-3">Açık Uçlu Sorular</h5>
                            
                            <?php
                            $oe_counter = 1;
                            foreach ($answers as $answer):
                                if ($answer['question_type'] !== 'multiple_choice'):
                            ?>
                                <div class="question-card">
                                    <div class="question-header">
                                        <div class="question-title">
                                            <?php echo $oe_counter++; ?>. <?php echo htmlspecialchars($answer['question_text']); ?>
                                        </div>
                                        <div class="question-meta">
                                            Şablon: <?php echo htmlspecialchars($answer['template_name'] ?? 'Bilinmiyor'); ?>
                                        </div>
                                    </div>
                                    <div class="answer-content">
                                        <?php if (!empty($answer['answer_text']) && $answer['answer_text'] !== '(Yanıtlanmadı)'): ?>
                                            <div class="answer-text">
                                                <strong>Metin Yanıtı:</strong><br>
                                                <?php echo nl2br(htmlspecialchars($answer['answer_text'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($answer['answer_file_path'])): ?>
                                            <div class="mb-3">
                                                <strong>Dosya Yanıtı:</strong><br>
                                                <a href="../<?php echo htmlspecialchars($answer['answer_file_path']); ?>" 
                                                   class="btn btn-outline-primary btn-sm mt-2" target="_blank">
                                                    <i class="bi bi-file-earmark-pdf me-1"></i>Dosyayı Görüntüle
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (empty($answer['answer_text']) && empty($answer['answer_file_path'])): ?>
                                            <p class="text-muted">Bu soru yanıtlanmamış.</p>
                                        <?php endif; ?>
                                        
                                        <!-- AI FEEDBACK BÖLÜMÜ - BURASI ÖNEMLİ -->
                                        <?php if (!empty($answer['answer_feedback'])): ?>
                                            <div class="ai-feedback-section">
                                                <div class="ai-feedback-header">
                                                    <i class="bi bi-robot me-2"></i>
                                                    AI Değerlendirmesi
                                                </div>
                                                <div class="ai-feedback-content">
                                                    <?php echo nl2br(htmlspecialchars($answer['answer_feedback'])); ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="ai-feedback-section">
                                                <div class="ai-feedback-header">
                                                    <i class="bi bi-robot me-2"></i>
                                                    AI Değerlendirmesi
                                                </div>
                                                <div class="no-ai-feedback">
                                                    Henüz AI değerlendirmesi yapılmamış
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-4 pt-3 border-top">
                                            <form action="" method="post">
                                                <input type="hidden" name="action" value="update_answer_score">
                                                <input type="hidden" name="answer_id" value="<?php echo $answer['id']; ?>">
                                                <div class="mb-3">
                                                    <div class="progress-bar-label">
                                                        <span class="label">Cevap Puanı</span>
                                                        <span class="value" id="answer_score_display_<?php echo $answer['id']; ?>">
                                                            <?php echo $answer['answer_score'] ?? 0; ?>
                                                        </span>
                                                    </div>
                                                    <input type="range" class="form-range" min="0" max="100" step="5" 
                                                           id="answer_score_<?php echo $answer['id']; ?>" 
                                                           name="answer_score" value="<?php echo $answer['answer_score'] ?? 0; ?>">
                                                </div>
                                                <div class="text-end">
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-check-lg me-1"></i>Puanı Kaydet
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <script>
                                    document.getElementById('answer_score_<?php echo $answer['id']; ?>').addEventListener('input', function() {
                                        document.getElementById('answer_score_display_<?php echo $answer['id']; ?>').textContent = this.value;
                                    });
                                </script>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-clipboard-x text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">Henüz test yanıtı yok</h5>
                            <p class="text-muted">Bu başvuru için henüz quiz tamamlanmamış.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- ADAY SEÇİM BÖLÜMÜ - Bu kısmı önceki gibi bırakıyorum -->
            <!-- İstatistikler -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <div class="stat-card stat-primary">
                        <div class="stat-title">Toplam Başvuru</div>
                        <div class="stat-value"><?php echo $total_applications; ?></div>
                        <div class="stat-desc">Tüm başvurular</div>
                        <div class="stat-icon">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <div class="stat-card stat-info">
                        <div class="stat-title">Bekleyen</div>
                        <div class="stat-value"><?php echo $new_applications; ?></div>
                        <div class="stat-desc">Değerlendirme bekleyen</div>
                        <div class="stat-icon">
                            <i class="bi bi-clock"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <div class="stat-card stat-success">
                        <div class="stat-title">Değerlendirilmiş</div>
                        <div class="stat-value"><?php echo $reviewed_applications; ?></div>
                        <div class="stat-desc">İncelenmiş başvurular</div>
                        <div class="stat-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card stat-warning">
                        <div class="stat-title">Ortalama CV Puanı</div>
                        <div class="stat-value"><?php echo $avg_cv_score; ?></div>
                        <div class="stat-desc">Puanlanan CV'lerde</div>
                        <div class="stat-icon">
                            <i class="bi bi-star"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Arama -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3 mb-md-0">
                    <input type="text" class="search-input" id="searchApplicants" 
                           placeholder="Aday ara (isim, e-posta, pozisyon...)">
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end gap-2">
                        <select class="form-select" id="statusFilter" style="max-width: 200px;">
                            <option value="all">Tüm Durumlar</option>
                            <option value="pending">Bekleyen</option>
                            <option value="completed">Tamamlanan</option>
                            <option value="reviewed">İncelenen</option>
                            <option value="rejected">Reddedilen</option>
                            <option value="accepted">Kabul Edilen</option>
                        </select>
                        <select class="form-select" id="jobFilter" style="max-width: 200px;">
                            <option value="all">Tüm Pozisyonlar</option>
                            <?php foreach ($jobs as $job): ?>
                                <option value="<?php echo $job['id']; ?>">
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Adaylar Listesi -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="bi bi-people me-2"></i>Adaylar</span>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($all_applications)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Aday</th>
                                        <th>Pozisyon</th>
                                        <th>Durum</th>
                                        <th>Test Puanı</th>
                                        <th>CV Puanı</th>
                                        <th>Başvuru Tarihi</th>
                                        <th class="text-end">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody id="applicantsList">
                                    <?php foreach($all_applications as $app): ?>
                                    <tr class="applicant-row" 
                                        data-name="<?php echo strtolower($app['first_name'] . ' ' . $app['last_name'] . ' ' . $app['email']); ?>" 
                                        data-status="<?php echo $app['status']; ?>"
                                        data-job="<?php echo $app['job_id']; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar me-3">
                                                    <?php echo strtoupper(substr($app['first_name'], 0, 1) . substr($app['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($app['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-medium"><?php echo htmlspecialchars($app['job_title']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($app['job_location']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge badge-<?php echo $app['status']; ?>">
                                                <?php 
                                                echo match($app['status']) {
                                                    'pending' => 'Beklemede',
                                                    'completed' => 'Tamamlandı',
                                                    'reviewed' => 'İncelendi',
                                                    'rejected' => 'Reddedildi',
                                                    'accepted' => 'Kabul Edildi',
                                                    default => ucfirst($app['status'])
                                                };
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                                $score = $app['score'] ?? 0;
                                                $score_class = '';
                                                if ($score >= 80) $score_class = 'text-success';
                                                else if ($score >= 50) $score_class = 'text-warning';
                                                else $score_class = 'text-danger';
                                            ?>
                                            <span class="fw-semibold <?php echo $score_class; ?>"><?php echo $score; ?></span>
                                        </td>
                                        <td>
                                            <?php
                                                $cv_score = $app['cv_score'] ?? 0;
                                                $cv_score_class = '';
                                                if ($cv_score >= 80) $cv_score_class = 'text-success';
                                                else if ($cv_score >= 50) $cv_score_class = 'text-warning';
                                                else if ($cv_score > 0) $cv_score_class = 'text-danger';
                                                else $cv_score_class = 'text-muted';
                                            ?>
                                            <span class="fw-semibold <?php echo $cv_score_class; ?>">
                                                <?php echo $cv_score > 0 ? $cv_score : '-'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d.m.Y H:i', strtotime($app['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <a href="?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye me-1"></i>İncele
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                            <h5>Henüz başvuru yok</h5>
                            <p class="text-muted">Sistemde kayıtlı başvuru bulunmamaktadır.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modals -->
    <?php if ($application_id > 0): ?>
    <!-- Not Modal -->
    <div class="modal fade" id="noteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-sticky-note me-2"></i>Not Ekle/Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="update_note">
                    <div class="modal-body">
                        <textarea class="form-control" name="admin_note" rows="6" 
                                  placeholder="Aday hakkında notlarınızı ekleyin..."><?php echo htmlspecialchars($application['admin_note'] ?? ''); ?></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- CV Puanlama Modal -->
    <div class="modal fade" id="cvScoreModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-star me-2"></i>CV Puanı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="update_cv_score">
                    <div class="modal-body">
                        <div class="progress-bar-label">
                            <span class="label">CV Puanı</span>
                            <span class="value" id="cv_score_display"><?php echo $application['cv_score'] ?? 0; ?></span>
                        </div>
                        <input type="range" class="form-range" min="0" max="100" step="5" 
                               id="cv_score" name="cv_score" value="<?php echo $application['cv_score'] ?? 0; ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- CV Feedback Modal -->
    <div class="modal fade" id="cvFeedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-chat-quote me-2"></i>CV Geri Bildirimi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="update_cv_feedback">
                    <div class="modal-body">
                        <textarea class="form-control" name="cv_feedback" rows="10" 
                                  placeholder="CV hakkında geri bildirim ekleyin..."><?php echo htmlspecialchars($application['cv_feedback'] ?? ''); ?></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Durum Değiştirme Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Başvuru Durumu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="update_status">
                    <div class="modal-body">
                        <select class="form-select" name="status" required>
                            <option value="pending" <?php echo ($application['status'] == 'pending') ? 'selected' : ''; ?>>Beklemede</option>
                            <option value="completed" <?php echo ($application['status'] == 'completed') ? 'selected' : ''; ?>>Tamamlandı</option>
                            <option value="reviewed" <?php echo ($application['status'] == 'reviewed') ? 'selected' : ''; ?>>İncelendi</option>
                            <option value="rejected" <?php echo ($application['status'] == 'rejected') ? 'selected' : ''; ?>>Reddedildi</option>
                            <option value="accepted" <?php echo ($application['status'] == 'accepted') ? 'selected' : ''; ?>>Kabul Edildi</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // CV puanı slider
        document.getElementById('cv_score')?.addEventListener('input', function() {
            document.getElementById('cv_score_display').textContent = this.value;
        });
        
        // Arama fonksiyonu
        document.getElementById('searchApplicants')?.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.applicant-row');
            
            rows.forEach(row => {
                const text = row.dataset.name;
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Durum filtresi
        document.getElementById('statusFilter')?.addEventListener('change', function() {
            const status = this.value;
            const rows = document.querySelectorAll('.applicant-row');
            
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // İş filtresi
        document.getElementById('jobFilter')?.addEventListener('change', function() {
            const jobId = this.value;
            const rows = document.querySelectorAll('.applicant-row');
            
            rows.forEach(row => {
                if (jobId === 'all' || row.dataset.job === jobId) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Kombine filtre fonksiyonu
        function applyFilters() {
            const searchTerm = document.getElementById('searchApplicants')?.value.toLowerCase() || '';
            const statusFilter = document.getElementById('statusFilter')?.value || 'all';
            const jobFilter = document.getElementById('jobFilter')?.value || 'all';
            const rows = document.querySelectorAll('.applicant-row');
            
            rows.forEach(row => {
                const name = row.dataset.name || '';
                const status = row.dataset.status || '';
                const job = row.dataset.job || '';
                
                const matchesSearch = name.includes(searchTerm);
                const matchesStatus = statusFilter === 'all' || status === statusFilter;
                const matchesJob = jobFilter === 'all' || job === jobFilter;
                
                if (matchesSearch && matchesStatus && matchesJob) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Event listener'ları güncelle
        document.getElementById('searchApplicants')?.addEventListener('keyup', applyFilters);
        document.getElementById('statusFilter')?.addEventListener('change', applyFilters);
        document.getElementById('jobFilter')?.addEventListener('change', applyFilters);
        
        // Score slider'lar için renk değişimi
        document.querySelectorAll('input[type="range"]').forEach(slider => {
            slider.addEventListener('input', function() {
                const value = this.value;
                const displayElement = document.getElementById(this.id + '_display');
                if (displayElement) {
                    displayElement.textContent = value;
                    
                    // Renk değişimi
                    displayElement.classList.remove('text-success', 'text-warning', 'text-danger');
                    if (value >= 80) {
                        displayElement.classList.add('text-success');
                    } else if (value >= 50) {
                        displayElement.classList.add('text-warning');
                    } else {
                        displayElement.classList.add('text-danger');
                    }
                }
            });
        });
        
        // AI Feedback section'ları için animasyon
        document.addEventListener('DOMContentLoaded', function() {
            const aiSections = document.querySelectorAll('.ai-feedback-section');
            aiSections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(10px)';
                section.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        // Form submission uyarıları
        let hasUnsavedChanges = false;
        
        document.querySelectorAll('textarea, input[type="range"]').forEach(element => {
            element.addEventListener('input', function() {
                hasUnsavedChanges = true;
            });
        });
        
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                hasUnsavedChanges = false;
            });
        });
        
        // Sayfa değişiminde uyarı
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                const confirmationMessage = 'Kaydedilmemiş değişiklikler var. Sayfayı terk etmek istediğinizden emin misiniz?';
                e.returnValue = confirmationMessage;
                return confirmationMessage;
            }
        });
        
        // Modal açılma animasyonları
        document.addEventListener('shown.bs.modal', function (e) {
            const modal = e.target;
            const firstInput = modal.querySelector('input, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        // Responsive tablo handling
        function handleTableResponsive() {
            const table = document.querySelector('.table-responsive');
            if (table && window.innerWidth < 768) {
                table.style.overflowX = 'auto';
            }
        }
        
        window.addEventListener('resize', handleTableResponsive);
        handleTableResponsive();
        
        // Print styles ekle
        const printStyles = `
            @media print {
                .navbar, .btn, .modal {
                    display: none !important;
                }
                .card {
                    break-inside: avoid;
                    box-shadow: none;
                    border: 1px solid #ddd;
                }
                .ai-feedback-section {
                    background-color: #f8f9fa !important;
                    -webkit-print-color-adjust: exact;
                }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>