<?php
// Output buffering başlat - header yönlendirme sorununu çözer
ob_start();

require_once 'config/db.php';
require_once 'includes/header.php';

$selected_job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// İş ilanlarını çek
$stmt = $db->prepare("SELECT * FROM jobs WHERE status = 'active' ORDER BY title");
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Seçili iş bilgisini al
$selected_job = null;
if ($selected_job_id > 0) {
    foreach ($jobs as $job) {
        if ($job['id'] == $selected_job_id) {
            $selected_job = $job;
            break;
        }
    }
}

// Form gönderildiyse işle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $job_id = (int)$_POST['job_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $gender = $_POST['gender'];
    $age = (int)$_POST['age'];
    $city = trim($_POST['city']);
    $salary = (float)$_POST['salary'];
    $education = trim($_POST['education']);
    $experience = (int)$_POST['experience']; // Yeni deneyim alanı
    
    // CV dosyasını yükle
    $cv_path = '';
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] === 0) {
        $upload_dir = 'uploads/cv/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['cv']['name']);
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['cv']['tmp_name'], $target_path)) {
            $cv_path = $target_path;
        }
    }
    
    if (empty($cv_path)) {
        // CV yükleme hatası
        $error = "CV yüklenirken bir hata oluştu.";
    } else {
        // Başvuruyu veritabanına kaydet
        $stmt = $db->prepare("INSERT INTO applications (job_id, first_name, last_name, phone, email, gender, age, city, salary_expectation, education, cv_path, experience) 
                             VALUES (:job_id, :first_name, :last_name, :phone, :email, :gender, :age, :city, :salary, :education, :cv_path, :experience)");
        
        $stmt->bindParam(':job_id', $job_id);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':gender', $gender);
        $stmt->bindParam(':age', $age);
        $stmt->bindParam(':city', $city);
        $stmt->bindParam(':salary', $salary);
        $stmt->bindParam(':education', $education);
        $stmt->bindParam(':cv_path', $cv_path);
        $stmt->bindParam(':experience', $experience); // Deneyim alanını ekle
        
        if ($stmt->execute()) {
            $application_id = $db->lastInsertId();
            // Quiz sayfasına yönlendir
            header("Location: quiz.php?application_id=$application_id&job_id=$job_id");
            exit;
        } else {
            $error = "Başvuru kaydedilirken bir hata oluştu.";
        }
    }
}

// Türkiye'nin büyük şehirlerini tanımla
$cities = [
    'İstanbul', 'Ankara', 'İzmir', 'Bursa', 'Antalya', 'Adana', 'Konya', 
    'Gaziantep', 'Şanlıurfa', 'Kocaeli', 'Mersin', 'Diyarbakır', 'Hatay', 
    'Manisa', 'Kayseri', 'Samsun', 'Balıkesir', 'Kahramanmaraş', 'Van', 'Aydın'
];

// Popüler bölümler
$departments = [
    'Bilgisayar Mühendisliği',
    'Elektrik-Elektronik Mühendisliği',
    'Makine Mühendisliği',
    'Endüstri Mühendisliği',
    'İşletme',
    'Ekonomi',
    'Psikoloji',
    'Hukuk',
    'Tıp',
    'Mimarlık',
    'İnşaat Mühendisliği',
    'Yazılım Mühendisliği',
    'Moleküler Biyoloji ve Genetik',
    'Uluslararası İlişkiler',
    'Grafik Tasarım',
    'İletişim',
    'Sosyoloji',
    'Kimya Mühendisliği',
    'Gıda Mühendisliği',
    'Yönetim Bilişim Sistemleri'
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İş Başvurusu - <?= $selected_job ? htmlspecialchars($selected_job['title']) : 'Pozisyon' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #ede9fe;
            --primary-dark: #3730a3;
            --secondary: #f8fafc;
            --success: #10b981;
            --success-light: #ecfdf5;
            --danger: #ef4444;
            --danger-light: #fef2f2;
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --info: #3b82f6;
            --info-light: #eff6ff;
            --text-dark: #0f172a;
            --text-light: #64748b;
            --text-muted: #94a3b8;
            --border-light: #e2e8f0;
            --border-medium: #cbd5e1;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --bg-glass: rgba(255, 255, 255, 0.8);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius: 16px;
            --radius-lg: 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--bg-light) 0%, #f1f5f9 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            font-feature-settings: 'cv02', 'cv03', 'cv04', 'cv11';
        }

        .application-wrapper {
            padding: 2.5rem 0;
            min-height: 100vh;
            position: relative;
        }

        .application-wrapper::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 100vh;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.03) 0%, rgba(59, 130, 246, 0.05) 100%);
            z-index: -1;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2.5rem;
            padding: 2rem 2.5rem;
            background: var(--bg-glass);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow);
            flex-wrap: wrap;
            gap: 2rem;
        }

        .job-info h1 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 0.75rem;
            color: var(--text-dark);
            letter-spacing: -0.025em;
            background: linear-gradient(135deg, var(--primary), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .job-meta {
            color: var(--text-light);
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
        }

        .job-meta .location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-white);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
        }

        .steps-indicator {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.875rem;
            position: relative;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            right: -0.75rem;
            top: 50%;
            transform: translateY(-50%);
            width: 0.5rem;
            height: 1px;
            background: var(--border-medium);
        }

        .step.active {
            color: var(--primary);
        }

        .step-number {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--border-light);
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .application-card {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
            backdrop-filter: blur(10px);
        }

        .application-card-header {
            padding: 2rem 2.5rem;
            background: linear-gradient(135deg, var(--primary-light) 0%, rgba(79, 70, 229, 0.1) 100%);
            border-bottom: 1px solid var(--border-light);
            position: relative;
        }

        .application-card-header::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--info));
        }

        .application-card-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 0.5rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.025em;
        }

        .application-card-header p {
            color: var(--text-light);
            margin: 0;
            font-size: 1rem;
            line-height: 1.7;
        }

        .application-card-body {
            padding: 2.5rem;
        }

        .form-section {
            margin-bottom: 2.5rem;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 2rem;
            position: relative;
        }

        .form-section:last-of-type {
            border-bottom: none;
            margin-bottom: 1rem;
        }

        .form-section h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
            padding: 0.75rem 1.25rem;
            background: linear-gradient(135deg, var(--bg-light) 0%, rgba(248, 250, 252, 0.8) 100%);
            border-radius: var(--radius);
            border: 1px solid var(--border-light);
            position: relative;
            letter-spacing: -0.025em;
        }

        .form-section h3::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, var(--primary), var(--info));
            border-radius: var(--radius) 0 0 var(--radius);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.625rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9375rem;
            letter-spacing: -0.025em;
        }

        .required {
            color: var(--danger);
            margin-left: 0.25rem;
            font-weight: 700;
        }

        .form-control, .form-select {
            display: block;
            width: 100%;
            padding: 0.875rem 1.125rem;
            font-size: 0.9375rem;
            font-weight: 500;
            line-height: 1.5;
            color: var(--text-dark);
            background: var(--bg-white);
            border: 2px solid var(--border-light);
            border-radius: var(--radius);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            font-family: inherit;
        }

        .form-control:focus, .form-select:focus {
            color: var(--text-dark);
            background: rgba(79, 70, 229, 0.02);
            border-color: var(--primary);
            outline: 0;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1), var(--shadow);
        }

        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 1;
        }

        .form-control.is-invalid, .form-select.is-invalid {
            border-color: var(--danger);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        .form-check {
            padding-left: 2rem;
            position: relative;
            margin-top: 1rem;
        }

        .form-check-input {
            position: absolute;
            left: 0;
            top: 0.375rem;
            width: 1.25rem;
            height: 1.25rem;
            margin-top: 0;
            border: 2px solid var(--border-medium);
            appearance: none;
            background: var(--bg-white);
            border-radius: 0.375rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .form-check-input:checked {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
        }

        .form-check-input:checked::after {
            content: "✓";
            position: absolute;
            top: -0.125rem;
            left: 0.25rem;
            font-size: 0.875rem;
            color: white;
            font-weight: 700;
        }

        .form-check-input:focus {
            outline: none;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .form-check-label {
            margin-bottom: 0;
            font-size: 0.9375rem;
            color: var(--text-dark);
            line-height: 1.6;
            font-weight: 500;
        }

        .privacy-check {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--info-light) 0%, rgba(59, 130, 246, 0.1) 100%);
            border-radius: var(--radius);
            margin-top: 1.5rem;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .custom-file-upload {
            position: relative;
        }

        .custom-file-upload input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 0.1px;
            height: 0.1px;
        }

        .file-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem 1.5rem;
            background: linear-gradient(135deg, var(--bg-light) 0%, rgba(248, 250, 252, 0.8) 100%);
            border: 2px dashed var(--border-medium);
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 600;
            color: var(--text-light);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
        }

        .file-label::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--primary-light) 0%, rgba(79, 70, 229, 0.1) 100%);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .file-label:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .file-label:hover::before {
            opacity: 1;
        }

        .file-label span, .file-label i {
            position: relative;
            z-index: 1;
        }

        .file-info {
            margin-top: 0.75rem;
            font-size: 0.8125rem;
            color: var(--text-muted);
            text-align: center;
            font-weight: 500;
        }

        .form-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-light);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            line-height: 1.5;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            user-select: none;
            border: 2px solid transparent;
            padding: 0.875rem 1.75rem;
            font-size: 0.9375rem;
            border-radius: 50px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            gap: 0.5rem;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: inherit;
            transition: all 0.3s ease;
            z-index: -1;
        }

        .btn-primary {
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-color: var(--primary);
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
            color: white;
        }

        .btn-primary::before {
            background: linear-gradient(135deg, var(--primary-dark), #312e81);
            opacity: 0;
        }

        .btn-primary:hover::before {
            opacity: 1;
        }

        .btn-light {
            color: var(--text-light);
            background: var(--bg-white);
            border-color: var(--border-light);
        }

        .btn-light:hover {
            background: var(--bg-light);
            border-color: var(--border-medium);
            color: var(--text-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-submit {
            padding: 1rem 2.5rem;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: -0.025em;
        }

        .btn-cancel {
            font-weight: 500;
        }

        .alert {
            position: relative;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            border-radius: var(--radius);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-danger {
            color: var(--danger);
            background: linear-gradient(135deg, var(--danger-light) 0%, rgba(239, 68, 68, 0.1) 100%);
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .application-card {
            animation: slideInUp 0.6s ease-out;
        }

        .form-section:nth-child(n) {
            animation: slideInUp 0.6s ease-out;
            animation-delay: calc(0.1s * var(--animation-order, 0));
        }

        /* Focus states for accessibility */
        .btn:focus,
        .form-control:focus,
        .form-select:focus,
        .form-check-input:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Selection styles */
        ::selection {
            background: rgba(79, 70, 229, 0.2);
            color: var(--text-dark);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-light);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-medium);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-light);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .application-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 1.5rem;
                gap: 1.5rem;
            }

            .steps-indicator {
                width: 100%;
                justify-content: space-between;
                gap: 0.5rem;
            }

            .step {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }

            .step:not(:last-child)::after {
                display: none;
            }

            .step-label {
                font-size: 0.75rem;
            }

            .application-card-body {
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
                gap: 0.75rem;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .job-info h1 {
                font-size: 1.5rem;
            }

            .application-card-header {
                padding: 1.5rem;
            }

            .form-section h3 {
                font-size: 1.125rem;
                padding: 0.625rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .application-wrapper {
                padding: 1.5rem 0;
            }

            .steps-indicator {
                flex-direction: column;
                width: 100%;
                gap: 0.75rem;
            }

            .step {
                flex-direction: row;
                justify-content: flex-start;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Modern Başvuru Sayfası -->
    <div class="application-wrapper">
        <div class="container">
            <!-- Başvuru Başlık -->
            <div class="application-header">
                <div class="job-info">
                    <h1><?= $selected_job ? htmlspecialchars($selected_job['title']) : 'İş Başvurusu' ?></h1>
                    <?php if ($selected_job): ?>
                        <div class="job-meta">
                            <span class="location"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($selected_job['location']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="steps-indicator">
                    <div class="step active">
                        <div class="step-number">1</div>
                        <div class="step-label">Başvuru Formu</div>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-label">Değerlendirme</div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-label">Tamamlandı</div>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Başvuru Formu -->
            <div class="application-card">
                <div class="application-card-header">
                    <h2><i class="bi bi-person-vcard"></i>Kişisel Bilgiler</h2>
                    <p>Başvurunuzu tamamlamak için aşağıdaki bilgileri dikkatli bir şekilde doldurun. Tüm alanlar zorunludur.</p>
                </div>
                <div class="application-card-body">
                    <form method="post" enctype="multipart/form-data" id="application-form">
                        <div class="form-section" style="--animation-order: 1">
                            <h3>Temel Bilgiler</h3>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="first_name">Adınız <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Adınızı girin" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="last_name">Soyadınız <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Soyadınızı girin" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone">Telefon Numarası <span class="required">*</span></label>
                                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="05xx xxx xx xx" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email">E-posta Adresi <span class="required">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="ornek@email.com" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="gender">Cinsiyet <span class="required">*</span></label>
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="">Seçiniz</option>
                                            <option value="male">Erkek</option>
                                            <option value="female">Kadın</option>
                                            <option value="other">Diğer</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="age">Yaş <span class="required">*</span></label>
                                        <input type="number" class="form-control" id="age" name="age" min="18" max="100" placeholder="Yaşınızı girin" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section" style="--animation-order: 2">
                            <h3>Lokasyon ve Eğitim</h3>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="city">Şehir <span class="required">*</span></label>
                                        <select class="form-select" id="city" name="city" required>
                                            <option value="">Şehir Seçiniz</option>
                                            <?php foreach ($cities as $city): ?>
                                                <option value="<?= htmlspecialchars($city) ?>"><?= htmlspecialchars($city) ?></option>
                                            <?php endforeach; ?>
                                            <option value="other">Diğer</option>
                                        </select>
                                        <div id="otherCity" class="mt-2 d-none">
                                            <input type="text" class="form-control" id="other_city_input" placeholder="Şehrinizi yazın">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="education">Eğitim/Mezun Olduğunuz Bölüm <span class="required">*</span></label>
                                        <select class="form-select" id="education" name="education" required>
                                            <option value="">Bölüm Seçiniz</option>
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?= htmlspecialchars($department) ?>"><?= htmlspecialchars($department) ?></option>
                                            <?php endforeach; ?>
                                            <option value="other">Diğer</option>
                                        </select>
                                        <div id="otherEducation" class="mt-2 d-none">
                                            <input type="text" class="form-control" id="other_education_input" placeholder="Bölümünüzü yazın">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section" style="--animation-order: 3">
                            <h3>İş Deneyimi ve Beklentiler</h3>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="experience">İş Deneyimi (Yıl) <span class="required">*</span></label>
                                        <select class="form-select" id="experience" name="experience" required>
                                            <option value="">Deneyim Seçiniz</option>
                                            <option value="0">Deneyim yok (0 yıl)</option>
                                            <option value="1">1 yıl</option>
                                            <option value="2">2 yıl</option>
                                            <option value="3">3 yıl</option>
                                            <option value="4">4 yıl</option>
                                            <option value="5">5 yıl</option>
                                            <option value="6">6 yıl</option>
                                            <option value="7">7 yıl</option>
                                            <option value="8">8 yıl</option>
                                            <option value="9">9 yıl</option>
                                            <option value="10">10 yıl</option>
                                            <option value="11">11-15 yıl</option>
                                            <option value="16">16-20 yıl</option>
                                            <option value="21">20+ yıl</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="salary">Maaş Beklentisi (TL) <span class="required">*</span></label>
                                        <input type="number" class="form-control" id="salary" name="salary" min="0" step="1000" placeholder="Aylık beklentinizi girin" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section" style="--animation-order: 4">
                            <h3>Dökümanlar ve Pozisyon</h3>
                            
                            <div class="form-group">
                                <label for="cv">CV (PDF) <span class="required">*</span></label>
                                <div class="custom-file-upload">
                                    <input type="file" class="form-control" id="cv" name="cv" accept=".pdf" required>
                                    <label for="cv" class="file-label">
                                        <i class="bi bi-upload"></i>
                                        <span>PDF Dosyası Seçin</span>
                                    </label>
                                    <div class="file-info">PDF formatında, maksimum 5MB</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="job_id">Başvurulan İş İlanı <span class="required">*</span></label>
                                <select class="form-select" id="job_id" name="job_id" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($jobs as $job): ?>
                                        <option value="<?= $job['id'] ?>" <?= ($job['id'] == $selected_job_id) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($job['title'] . ' - ' . $job['location']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-check privacy-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    Kişisel verilerimin, başvuru değerlendirme süreçlerinde kullanılmasını kabul ediyorum. <span class="required">*</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-submit">
                                <span>Başvuruyu Tamamla ve Sorulara Geç</span>
                                <i class="bi bi-arrow-right-circle"></i>
                            </button>
                            <a href="index.php" class="btn btn-light btn-cancel">
                                <i class="bi bi-arrow-left"></i>
                                İptal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Dosya seçiminde dosya adını göster
            const cvInput = document.getElementById('cv');
            const fileLabel = document.querySelector('.file-label span');
            const originalText = fileLabel.textContent;
            
            cvInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const fileName = this.files[0].name;
                    const displayName = fileName.length > 25 ? fileName.substring(0, 22) + '...' : fileName;
                    fileLabel.textContent = displayName;
                    
                    // Visual feedback
                    const fileUpload = this.closest('.custom-file-upload');
                    const label = fileUpload.querySelector('.file-label');
                    label.style.background = 'linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%)';
                    label.style.borderColor = '#10b981';
                    label.style.color = '#10b981';
                    
                    // Dosya boyutu kontrolü
                    const fileSize = this.files[0].size / 1024 / 1024; // MB cinsinden
                    if (fileSize > 5) {
                        alert('Dosya boyutu 5MB\'den büyük olamaz!');
                        this.value = '';
                        fileLabel.textContent = originalText;
                        // Reset styling
                        label.style.background = '';
                        label.style.borderColor = '';
                        label.style.color = '';
                    }
                } else {
                    fileLabel.textContent = originalText;
                }
            });

            // "Diğer" şehir seçildiğinde input göster
            const citySelect = document.getElementById('city');
            const otherCityDiv = document.getElementById('otherCity');
            const otherCityInput = document.getElementById('other_city_input');
            
            citySelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    otherCityDiv.classList.remove('d-none');
                    otherCityInput.setAttribute('name', 'city');
                    otherCityInput.setAttribute('required', 'required');
                    this.removeAttribute('name');
                } else {
                    otherCityDiv.classList.add('d-none');
                    otherCityInput.removeAttribute('name');
                    otherCityInput.removeAttribute('required');
                    this.setAttribute('name', 'city');
                }
            });
            
            // "Diğer" eğitim seçildiğinde input göster
            const educationSelect = document.getElementById('education');
            const otherEducationDiv = document.getElementById('otherEducation');
            const otherEducationInput = document.getElementById('other_education_input');
            
            educationSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    otherEducationDiv.classList.remove('d-none');
                    otherEducationInput.setAttribute('name', 'education');
                    otherEducationInput.setAttribute('required', 'required');
                    this.removeAttribute('name');
                } else {
                    otherEducationDiv.classList.add('d-none');
                    otherEducationInput.removeAttribute('name');
                    otherEducationInput.removeAttribute('required');
                    this.setAttribute('name', 'education');
                }
            });
            
            // Enhanced form validation with better visual feedback
            const applicationForm = document.getElementById('application-form');
            applicationForm.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let allValid = true;
                let firstInvalidField = null;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        allValid = false;
                        field.classList.add('is-invalid');
                        if (!firstInvalidField) {
                            firstInvalidField = field;
                        }
                        
                        // Add shake animation
                        field.style.animation = 'shake 0.5s ease-in-out';
                        setTimeout(() => {
                            field.style.animation = '';
                        }, 500);
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                // Kontrol kutusunu kontrol et
                const termsCheckbox = document.getElementById('terms');
                if (!termsCheckbox.checked) {
                    allValid = false;
                    termsCheckbox.classList.add('is-invalid');
                    if (!firstInvalidField) {
                        firstInvalidField = termsCheckbox;
                    }
                } else {
                    termsCheckbox.classList.remove('is-invalid');
                }
                
                if (!allValid) {
                    e.preventDefault();
                    
                    // Scroll to first invalid field
                    if (firstInvalidField) {
                        firstInvalidField.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        firstInvalidField.focus();
                    }
                    
                    // Show error notification
                    const notification = document.createElement('div');
                    notification.innerHTML = `
                        <div style="position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #fef2f2, rgba(239, 68, 68, 0.1)); 
                                    border: 1px solid rgba(239, 68, 68, 0.3); color: #dc2626; padding: 1rem 1.5rem; 
                                    border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 10000;
                                    animation: slideInRight 0.3s ease;">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            Lütfen tüm zorunlu alanları doldurun.
                        </div>
                    `;
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 4000);
                } else {
                    // Show loading state
                    const submitBtn = this.querySelector('.btn-submit');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = `
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span>Gönderiliyor...</span>
                    `;
                    submitBtn.disabled = true;
                }
            });
            
            // Add shake animation keyframes
            const style = document.createElement('style');
            style.textContent = `
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-5px); }
                    75% { transform: translateX(5px); }
                }
                
                @keyframes slideInRight {
                    from {
                        opacity: 0;
                        transform: translateX(100%);
                    }
                    to {
                        opacity: 1;
                        transform: translateX(0);
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Real-time validation feedback
            const inputs = document.querySelectorAll('.form-control, .form-select');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.hasAttribute('required') && this.value.trim()) {
                        this.classList.remove('is-invalid');
                    }
                });
                
                input.addEventListener('blur', function() {
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('is-invalid');
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php 
require_once 'includes/footer.php';
// Output buffer içeriğini gönder ve buffer'ı temizle
ob_end_flush();
?>