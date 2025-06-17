<?php
// Output buffering başlat
ob_start();

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/db.php';

$limit_per_job_answers = 3; // Her iş ilanı için gösterilecek en düşük/yüksek puanlı cevap sayısı

// --- AKTİF İLANLAR İÇİN VERİLER ---

// 1. Haftanın günlerine göre başvuru sayısı (Aktif İlanlar)
$applications_by_weekday_query_active = "
    SELECT 
        DAYOFWEEK(a.created_at) as day_num,
        CASE DAYOFWEEK(a.created_at)
            WHEN 1 THEN 'Pazar' WHEN 2 THEN 'Pazartesi' WHEN 3 THEN 'Salı'
            WHEN 4 THEN 'Çarşamba' WHEN 5 THEN 'Perşembe' WHEN 6 THEN 'Cuma'
            WHEN 7 THEN 'Cumartesi'
        END as day_name,
        COUNT(a.id) as application_count
    FROM 
        applications a
    JOIN
        jobs j ON a.job_id = j.id
    WHERE 
        j.status = 'active'
    GROUP BY 
        day_num, day_name
    ORDER BY 
        day_num
";
try {
    $stmt_active = $db->query($applications_by_weekday_query_active);
    $applications_by_weekday_active = $stmt_active->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $applications_by_weekday_active = [];
    error_log("Error in applications_by_weekday_query_active: " . $e->getMessage());
}

// Total applications count for active jobs
try {
    $stmt_active = $db->query("SELECT COUNT(a.id) as count FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.status = 'active'");
    $total_applications_active = $stmt_active->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $total_applications_active = 0;
    error_log("Error getting total_applications_active: " . $e->getMessage());
}

// 2. AKTİF İLANLAR İÇİN GENEL EN DÜŞÜK PUANLI AÇIK UÇLU CEVAPLAR (TOP 10)
$lowest_score_open_ended_query_active = "
    SELECT 
        tq.id AS question_id,
        tq.question_text,
        qt.template_name,
        j.title AS job_title,
        app.id AS application_id,
        CONCAT(app.first_name, ' ', app.last_name) AS applicant_name,
        aa.answer_score,
        aa.answer_text
    FROM 
        application_answers aa
    JOIN 
        template_questions tq ON aa.question_id = tq.id
    JOIN 
        question_templates qt ON tq.template_id = qt.id
    JOIN 
        applications app ON aa.application_id = app.id
    JOIN 
        jobs j ON app.job_id = j.id
    WHERE 
        aa.option_id IS NULL 
        AND aa.answer_score IS NOT NULL
        AND j.status = 'active'
        AND tq.question_type = 'open_ended'
    ORDER BY 
        aa.answer_score ASC, aa.created_at DESC
    LIMIT 10
";
try {
    $stmt_active_lowest_open_ended = $db->query($lowest_score_open_ended_query_active);
    $lowest_score_open_ended_active = $stmt_active_lowest_open_ended->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lowest_score_open_ended_active = [];
    error_log("Error in lowest_score_open_ended_query_active: " . $e->getMessage());
}

// 3. AKTİF İLANLAR İÇİN GENEL EN YÜKSEK PUANLI AÇIK UÇLU CEVAPLAR (TOP 10)
$highest_score_open_ended_query_active = "
    SELECT 
        tq.id AS question_id,
        tq.question_text,
        qt.template_name,
        j.title AS job_title,
        app.id AS application_id,
        CONCAT(app.first_name, ' ', app.last_name) AS applicant_name,
        aa.answer_score,
        aa.answer_text
    FROM 
        application_answers aa
    JOIN 
        template_questions tq ON aa.question_id = tq.id
    JOIN 
        question_templates qt ON tq.template_id = qt.id
    JOIN 
        applications app ON aa.application_id = app.id
    JOIN 
        jobs j ON app.job_id = j.id
    WHERE 
        aa.option_id IS NULL 
        AND aa.answer_score IS NOT NULL
        AND j.status = 'active'
        AND tq.question_type = 'open_ended'
    ORDER BY 
        aa.answer_score DESC, aa.created_at DESC
    LIMIT 10
";
try {
    $stmt_active_highest_open_ended = $db->query($highest_score_open_ended_query_active);
    $highest_score_open_ended_active = $stmt_active_highest_open_ended->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $highest_score_open_ended_active = [];
    error_log("Error in highest_score_open_ended_query_active: " . $e->getMessage());
}

// 4. AKTİF İLANLAR - İŞ İLANINA GÖRE EN DÜŞÜK PUANLI AÇIK UÇLU CEVAPLAR
$open_ended_scores_by_job_lowest_query_active = "
    SELECT * FROM (
        SELECT
            j.id AS job_id,
            j.title AS job_title,
            tq.id AS question_id,
            tq.question_text,
            qt.template_name,
            qt.id as template_id,
            app.id AS application_id,
            CONCAT(app.first_name, ' ', app.last_name) AS applicant_name,
            aa.answer_score,
            aa.answer_text,
            ROW_NUMBER() OVER (PARTITION BY j.id ORDER BY aa.answer_score ASC, aa.created_at DESC) as rn
        FROM
            application_answers aa
        JOIN
            template_questions tq ON aa.question_id = tq.id
        JOIN
            question_templates qt ON tq.template_id = qt.id
        JOIN
            applications app ON aa.application_id = app.id
        JOIN
            jobs j ON app.job_id = j.id
        WHERE
            aa.option_id IS NULL
            AND aa.answer_score IS NOT NULL
            AND j.status = 'active'
            AND tq.question_type = 'open_ended'
    ) ranked_answers
    WHERE rn <= {$limit_per_job_answers}
    ORDER BY job_title, rn
";
try {
    $stmt_active = $db->query($open_ended_scores_by_job_lowest_query_active);
    $open_ended_scores_by_job_lowest_temp_active = $stmt_active->fetchAll(PDO::FETCH_ASSOC);
    $open_ended_scores_by_job_lowest_active = [];
    foreach ($open_ended_scores_by_job_lowest_temp_active as $row) {
        $open_ended_scores_by_job_lowest_active[$row['job_id']]['job_title'] = $row['job_title'];
        $open_ended_scores_by_job_lowest_active[$row['job_id']]['answers'][] = $row;
    }
} catch (PDOException $e) {
    $open_ended_scores_by_job_lowest_active = [];
    error_log("Error in open_ended_scores_by_job_lowest_query_active: " . $e->getMessage());
}

// 5. AKTİF İLANLAR - İŞ İLANINA GÖRE EN YÜKSEK PUANLI AÇIK UÇLU CEVAPLAR
$open_ended_scores_by_job_highest_query_active = "
    SELECT * FROM (
        SELECT
            j.id AS job_id,
            j.title AS job_title,
            tq.id AS question_id,
            tq.question_text,
            qt.template_name,
            qt.id as template_id,
            app.id AS application_id,
            CONCAT(app.first_name, ' ', app.last_name) AS applicant_name,
            aa.answer_score,
            aa.answer_text,
            ROW_NUMBER() OVER (PARTITION BY j.id ORDER BY aa.answer_score DESC, aa.created_at DESC) as rn
        FROM
            application_answers aa
        JOIN
            template_questions tq ON aa.question_id = tq.id
        JOIN
            question_templates qt ON tq.template_id = qt.id
        JOIN
            applications app ON aa.application_id = app.id
        JOIN
            jobs j ON app.job_id = j.id
        WHERE
            aa.option_id IS NULL
            AND aa.answer_score IS NOT NULL
            AND j.status = 'active'
            AND tq.question_type = 'open_ended'
    ) ranked_answers
    WHERE rn <= {$limit_per_job_answers}
    ORDER BY job_title, rn
";
try {
    $stmt_active = $db->query($open_ended_scores_by_job_highest_query_active);
    $open_ended_scores_by_job_highest_temp_active = $stmt_active->fetchAll(PDO::FETCH_ASSOC);
    $open_ended_scores_by_job_highest_active = [];
    foreach ($open_ended_scores_by_job_highest_temp_active as $row) {
        $open_ended_scores_by_job_highest_active[$row['job_id']]['job_title'] = $row['job_title'];
        $open_ended_scores_by_job_highest_active[$row['job_id']]['answers'][] = $row;
    }
} catch (PDOException $e) {
    $open_ended_scores_by_job_highest_active = [];
    error_log("Error in open_ended_scores_by_job_highest_query_active: " . $e->getMessage());
}


// --- TÜM ZAMANLAR İÇİN VERİLER (TÜM İLANLAR) ---

// 1. Haftanın günlerine göre başvuru sayısı (Tüm Zamanlar)
$applications_by_weekday_query_all_time = "
    SELECT 
        DAYOFWEEK(created_at) as day_num,
        CASE DAYOFWEEK(created_at)
            WHEN 1 THEN 'Pazar' WHEN 2 THEN 'Pazartesi' WHEN 3 THEN 'Salı'
            WHEN 4 THEN 'Çarşamba' WHEN 5 THEN 'Perşembe' WHEN 6 THEN 'Cuma'
            WHEN 7 THEN 'Cumartesi'
        END as day_name,
        COUNT(*) as application_count
    FROM 
        applications
    GROUP BY 
        day_num, day_name
    ORDER BY 
        day_num
";
try {
    $stmt_all_time = $db->query($applications_by_weekday_query_all_time);
    $applications_by_weekday_all_time = $stmt_all_time->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $applications_by_weekday_all_time = [];
    error_log("Error in applications_by_weekday_query_all_time: " . $e->getMessage());
}

// Total applications count for all time
try {
    $stmt_all_time = $db->query("SELECT COUNT(*) as count FROM applications");
    $total_applications_all_time = $stmt_all_time->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $total_applications_all_time = 0;
    error_log("Error getting total_applications_all_time: " . $e->getMessage());
}

// 2. TÜM ZAMANLAR İÇİN GENEL EN DÜŞÜK PUANLI AÇIK UÇLU CEVAPLAR (TOP 10)
$lowest_score_open_ended_query_all_time = "
    SELECT 
        tq.id AS question_id,
        tq.question_text,
        qt.template_name,
        j.title AS job_title,
        app.id AS application_id,
        CONCAT(app.first_name, ' ', app.last_name) AS applicant_name,
        aa.answer_score,
        aa.answer_text
    FROM 
        application_answers aa
    JOIN 
        template_questions tq ON aa.question_id = tq.id
    JOIN 
        question_templates qt ON tq.template_id = qt.id
    JOIN 
        applications app ON aa.application_id = app.id
    JOIN 
        jobs j ON app.job_id = j.id
    WHERE 
        aa.option_id IS NULL 
        AND aa.answer_score IS NOT NULL
        AND tq.question_type = 'open_ended'
    ORDER BY 
        aa.answer_score ASC, aa.created_at DESC
    LIMIT 10
";
try {
    $stmt_all_time_lowest_open_ended = $db->query($lowest_score_open_ended_query_all_time);
    $lowest_score_open_ended_all_time = $stmt_all_time_lowest_open_ended->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lowest_score_open_ended_all_time = [];
    error_log("Error in lowest_score_open_ended_query_all_time: " . $e->getMessage());
}

// 3. TÜM ZAMANLAR İÇİN GENEL EN YÜKSEK PUANLI AÇIK UÇLU CEVAPLAR (TOP 10)
$highest_score_open_ended_query_all_time = "
    SELECT 
        tq.id AS question_id,
        tq.question_text,
        qt.template_name,
        j.title AS job_title,
        app.id AS application_id,
        CONCAT(app.first_name, ' ', app.last_name) AS applicant_name,
        aa.answer_score,
        aa.answer_text
    FROM 
        application_answers aa
    JOIN 
        template_questions tq ON aa.question_id = tq.id
    JOIN 
        question_templates qt ON tq.template_id = qt.id
    JOIN 
        applications app ON aa.application_id = app.id
    JOIN 
        jobs j ON app.job_id = j.id
    WHERE 
        aa.option_id IS NULL 
        AND aa.answer_score IS NOT NULL
        AND tq.question_type = 'open_ended'
    ORDER BY 
        aa.answer_score DESC, aa.created_at DESC
    LIMIT 10
";
try {
    $stmt_all_time_highest_open_ended = $db->query($highest_score_open_ended_query_all_time);
    $highest_score_open_ended_all_time = $stmt_all_time_highest_open_ended->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $highest_score_open_ended_all_time = [];
    error_log("Error in highest_score_open_ended_query_all_time: " . $e->getMessage());
}

// 4. TÜM ZAMANLAR - İŞ İLANINA GÖRE EN DÜŞÜK PUANLI AÇIK UÇLU CEVAPLAR
$open_ended_scores_by_job_lowest_query_all_time = "
    SELECT * FROM (
        SELECT
            j.id AS job_id,
            j.title AS job_title,
            tq.id AS question_id,
            tq.question_text,
            qt.template_name,
            qt.id as template_id,
            app.id AS application_id,
            CONCAT(app.first_name, ' ', app.last_name) AS applicant_name,
            aa.answer_score,
            aa.answer_text,
            ROW_NUMBER() OVER (PARTITION BY j.id ORDER BY aa.answer_score ASC, aa.created_at DESC) as rn
        FROM
            application_answers aa
        JOIN
            template_questions tq ON aa.question_id = tq.id
        JOIN
            question_templates qt ON tq.template_id = qt.id
        JOIN
            applications app ON aa.application_id = app.id
        JOIN
            jobs j ON app.job_id = j.id
        WHERE
            aa.option_id IS NULL
            AND aa.answer_score IS NOT NULL
            AND tq.question_type = 'open_ended'
    ) ranked_answers
    WHERE rn <= {$limit_per_job_answers}
    ORDER BY job_title, rn
";
try {
    $stmt_all_time = $db->query($open_ended_scores_by_job_lowest_query_all_time);
    $open_ended_scores_by_job_lowest_temp_all_time = $stmt_all_time->fetchAll(PDO::FETCH_ASSOC);
    $open_ended_scores_by_job_lowest_all_time = [];
    foreach ($open_ended_scores_by_job_lowest_temp_all_time as $row) {
        $open_ended_scores_by_job_lowest_all_time[$row['job_id']]['job_title'] = $row['job_title'];
        $open_ended_scores_by_job_lowest_all_time[$row['job_id']]['answers'][] = $row;
    }
} catch (PDOException $e) {
    $open_ended_scores_by_job_lowest_all_time = [];
    error_log("Error in open_ended_scores_by_job_lowest_query_all_time: " . $e->getMessage());
}

// 5. TÜM ZAMANLAR - İŞ İLANINA GÖRE EN YÜKSEK PUANLI AÇIK UÇLU CEVAPLAR
$open_ended_scores_by_job_highest_query_all_time = "
    SELECT * FROM (
        SELECT
            j.id AS job_id,
            j.title AS job_title,
            tq.id AS question_id,
            tq.question_text,
            qt.template_name,
            qt.id as template_id,
            app.id AS application_id,
            CONCAT(app.first_name, ' ', app.last_name) AS applicant_name,
            aa.answer_score,
            aa.answer_text,
            ROW_NUMBER() OVER (PARTITION BY j.id ORDER BY aa.answer_score DESC, aa.created_at DESC) as rn
        FROM
            application_answers aa
        JOIN
            template_questions tq ON aa.question_id = tq.id
        JOIN
            question_templates qt ON tq.template_id = qt.id
        JOIN
            applications app ON aa.application_id = app.id
        JOIN
            jobs j ON app.job_id = j.id
        WHERE
            aa.option_id IS NULL
            AND aa.answer_score IS NOT NULL
            AND tq.question_type = 'open_ended'
    ) ranked_answers
    WHERE rn <= {$limit_per_job_answers}
    ORDER BY job_title, rn
";
try {
    $stmt_all_time = $db->query($open_ended_scores_by_job_highest_query_all_time);
    $open_ended_scores_by_job_highest_temp_all_time = $stmt_all_time->fetchAll(PDO::FETCH_ASSOC);
    $open_ended_scores_by_job_highest_all_time = [];
    foreach ($open_ended_scores_by_job_highest_temp_all_time as $row) {
        $open_ended_scores_by_job_highest_all_time[$row['job_id']]['job_title'] = $row['job_title'];
        $open_ended_scores_by_job_highest_all_time[$row['job_id']]['answers'][] = $row;
    }
} catch (PDOException $e) {
    $open_ended_scores_by_job_highest_all_time = [];
    error_log("Error in open_ended_scores_by_job_highest_query_all_time: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Açık Uçlu Soru ve Başvuru Analizi | İş Başvuru Sistemi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
      <style>
        :root {
            --primary: #4361ee;
            --primary-hover: #3a56d4;
            --primary-light: rgba(67, 97, 238, 0.1);
            --secondary: #747f8d;
            --success: #2ecc71;
            --success-light: rgba(46, 204, 113, 0.1);
            --danger: #e74c3c;
            --danger-light: rgba(231, 76, 60, 0.1);
            --warning: #f39c12;
            --warning-light: rgba(243, 156, 18, 0.1);
            --info: #3498db;
            --info-light: rgba(52, 152, 219, 0.1);
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
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 0.5rem;
            color: var(--primary);
        }
          .card-body {
            padding: 1.25rem;
        }
        
        .chart-container {
            position: relative;
            width: 100%;
            height: 300px;
        }
                
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            font-weight: 600;
            color: var(--secondary);
            border-top: none;
            padding: 1rem 0.75rem;
            vertical-align: bottom;
            border-bottom: 2px solid var(--card-border);
        }
        
        .table td {
            vertical-align: middle;
            padding: 0.75rem;
            border-top: 1px solid var(--card-border);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.01);
        }
        
        .dashboard-container {
            margin-bottom: 2rem;
        }        .section-divider {
            margin-top: 3rem;
            margin-bottom: 2rem;
            border-top: 2px dashed var(--card-border);
            padding-top: 2rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--body-color);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 0.5rem;
            color: var(--primary);
        }
        
        .metric-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .badge-primary { background-color: var(--primary-light); color: var(--primary); }
        .badge-success { background-color: var(--success-light); color: var(--success); }
        .badge-warning { background-color: var(--warning-light); color: var(--warning); }
        .badge-danger { background-color: var(--danger-light); color: var(--danger); }
        .badge-info { background-color: var(--info-light); color: var(--info); }
        
        .job-detail-card {
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1.5rem;
            background-color: var(--card-bg);
            box-shadow: var(--card-shadow);
            border: 1px solid var(--card-border);
            height: 100%;
        }
        
        .job-detail-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--card-border);
            background-color: var(--primary-light);
        }
        
        .job-detail-title {
            font-weight: 600;
            margin-bottom: 0;
            color: var(--primary);
        }
        
        .job-detail-body { padding: 1.25rem; }
        
        .error-container {
            text-align: center; padding: 2rem; background-color: var(--light);
            border-radius: 0.75rem; margin-bottom: 1.5rem;
        }
        .error-icon { font-size: 3rem; color: var(--warning); margin-bottom: 1rem; }
        .error-title { font-weight: 600; margin-bottom: 0.5rem; font-size: 1.25rem; }
        .error-message { color: var(--text-secondary); margin-bottom: 1.5rem; }
        
        .score-badge {
            padding: 0.3em 0.6em;
            font-size: 0.85em;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: .25rem;
        }
        .score-low { background-color: var(--danger); }
        .score-medium { background-color: var(--warning); color: var(--dark) !important; }
        .score-high { background-color: var(--success); }


        @media (max-width: 991.98px) {
            .admin-navbar .navbar-nav { padding-top: 15px; }
            .admin-navbar .nav-link { padding: 10px; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="page-header">
        <div class="container">
            <h1 class="page-title">Açık Uçlu Soru ve Başvuru Analizi</h1>
            <p class="page-subtitle">Açık uçlu cevap puanları, iş ilanı bazında performans ve haftalık başvuru istatistikleri.</p>
        </div>
    </div>

    <div class="container">

        <!-- ============================================================================== -->
        <!-- AKTİF İLANLAR İÇİN ANALİZLER -->
        <!-- ============================================================================== -->
        <div class="dashboard-container">
            <h2 class="section-title mb-3" style="font-size: 1.5rem; color: var(--primary); border-bottom: 2px solid var(--primary-light); padding-bottom: 0.5rem;">
                <i class="bi bi-activity"></i> Aktif İlanlar İçin Analizler
            </h2>

            <!-- Genel En Düşük Puanlı Açık Uçlu Cevaplar (Aktif İlanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-emoji-frown"></i> Genel En Düşük Puanlı Açık Uçlu Cevaplar (Aktif İlanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($lowest_score_open_ended_active) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th> <th width="25%">Soru</th> <th width="15%">Aday</th>
                                        <th width="15%">İlan</th> <th width="10%">Şablon</th> <th width="5%">Puan</th>
                                        <th width="20%">Cevap</th> <th width="5%">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($lowest_score_open_ended_active as $index => $answer): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars(mb_strlen($answer['question_text']) > 50 ? mb_substr($answer['question_text'], 0, 50) . '...' : $answer['question_text']) ?></td>
                                            <td><?= htmlspecialchars($answer['applicant_name']) ?></td>
                                            <td><span class="metric-badge badge-primary"><?= htmlspecialchars($answer['job_title']) ?></span></td>
                                            <td><span class="metric-badge badge-info"><?= htmlspecialchars($answer['template_name']) ?></span></td>
                                            <td>
                                                <?php
                                                    $score = $answer['answer_score'];
                                                    $score_class = 'score-high'; // Default
                                                    if ($score < 40) $score_class = 'score-low';
                                                    else if ($score < 70) $score_class = 'score-medium';
                                                ?>
                                                <span class="score-badge <?= $score_class ?>"><?= $score ?></span>
                                            </td>
                                            <td><?= htmlspecialchars(mb_strlen($answer['answer_text']) > 40 ? mb_substr($answer['answer_text'], 0, 40) . '...' : $answer['answer_text']) ?></td>
                                            <td><a href="application-detail2.php?id=<?= $answer['application_id'] ?>#question-<?= $answer['question_id'] ?>" class="btn btn-sm btn-outline-info" title="Başvuruyu Görüntüle"><i class="bi bi-eye"></i></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Aktif ilanlar için düşük puanlı açık uçlu cevap bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Genel En Yüksek Puanlı Açık Uçlu Cevaplar (Aktif İlanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-emoji-smile"></i> Genel En Yüksek Puanlı Açık Uçlu Cevaplar (Aktif İlanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($highest_score_open_ended_active) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                     <tr>
                                        <th width="5%">#</th> <th width="25%">Soru</th> <th width="15%">Aday</th>
                                        <th width="15%">İlan</th> <th width="10%">Şablon</th> <th width="5%">Puan</th>
                                        <th width="20%">Cevap</th> <th width="5%">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($highest_score_open_ended_active as $index => $answer): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars(mb_strlen($answer['question_text']) > 50 ? mb_substr($answer['question_text'], 0, 50) . '...' : $answer['question_text']) ?></td>
                                            <td><?= htmlspecialchars($answer['applicant_name']) ?></td>
                                            <td><span class="metric-badge badge-primary"><?= htmlspecialchars($answer['job_title']) ?></span></td>
                                            <td><span class="metric-badge badge-info"><?= htmlspecialchars($answer['template_name']) ?></span></td>
                                            <td>
                                                <?php
                                                    $score = $answer['answer_score'];
                                                    $score_class = 'score-high'; // Default
                                                    if ($score < 40) $score_class = 'score-low';
                                                    else if ($score < 70) $score_class = 'score-medium';
                                                ?>
                                                <span class="score-badge <?= $score_class ?>"><?= $score ?></span>
                                            </td>
                                            <td><?= htmlspecialchars(mb_strlen($answer['answer_text']) > 40 ? mb_substr($answer['answer_text'], 0, 40) . '...' : $answer['answer_text']) ?></td>
                                            <td><a href="application-detail2.php?id=<?= $answer['application_id'] ?>#question-<?= $answer['question_id'] ?>" class="btn btn-sm btn-outline-info" title="Başvuruyu Görüntüle"><i class="bi bi-eye"></i></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Aktif ilanlar için yüksek puanlı açık uçlu cevap bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Haftanın Günlerine Göre Başvuru Sayısı (Aktif İlanlar) -->
            
            
            <!-- İş İlanına Göre Açık Uçlu Cevap Puanları (Aktif İlanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-briefcase"></i> İş İlanlarına Göre Açık Uçlu Cevap Puanları (Aktif İlanlar)</h3>
            <div class="row">
                <?php 
                    $all_job_ids_active = array_unique(array_merge(array_keys($open_ended_scores_by_job_lowest_active), array_keys($open_ended_scores_by_job_highest_active)));
                    if (count($all_job_ids_active) > 0):
                        // Get all job titles for these ids to avoid issues if one list is empty for a job
                        $job_titles_active = [];
                        foreach($open_ended_scores_by_job_lowest_active as $job_id => $data) $job_titles_active[$job_id] = $data['job_title'];
                        foreach($open_ended_scores_by_job_highest_active as $job_id => $data) $job_titles_active[$job_id] = $data['job_title'];

                        foreach ($job_titles_active as $job_id => $job_title):
                ?>
                    <div class="col-lg-6 mb-4">
                        <div class="job-detail-card">
                            <div class="job-detail-header"><h5 class="job-detail-title"><?= htmlspecialchars($job_title) ?></h5></div>
                            <div class="job-detail-body">
                                <?php if (isset($open_ended_scores_by_job_lowest_active[$job_id]) && count($open_ended_scores_by_job_lowest_active[$job_id]['answers']) > 0): ?>
                                    <h6 class="mb-2 text-danger"><i class="bi bi-arrow-down-circle"></i> En Düşük Puanlı <?= $limit_per_job_answers ?> Cevap</h6>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm table-hover">
                                            <thead><tr><th>Aday</th><th>Soru</th><th>Puan</th><th>Cevap</th><th>İşlem</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($open_ended_scores_by_job_lowest_active[$job_id]['answers'] as $answer): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($answer['applicant_name']) ?></td>
                                                        <td><small><?= htmlspecialchars(mb_strlen($answer['question_text']) > 30 ? mb_substr($answer['question_text'], 0, 30) . '...' : $answer['question_text']) ?></small></td>
                                                        <td><span class="score-badge score-low"><?= $answer['answer_score'] ?></span></td>
                                                        <td><small><?= htmlspecialchars(mb_strlen($answer['answer_text']) > 25 ? mb_substr($answer['answer_text'], 0, 25) . '...' : $answer['answer_text']) ?></small></td>
                                                        <td><a href="application-detail2.php?id=<?= $answer['application_id'] ?>#question-<?= $answer['question_id'] ?>" class="btn btn-xs btn-outline-info" title="Görüntüle"><i class="bi bi-eye"></i></a></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-2 small">Bu ilan için en düşük puanlı açık uçlu cevap bulunamadı.</p>
                                <?php endif; ?>

                                <?php if (isset($open_ended_scores_by_job_highest_active[$job_id]) && count($open_ended_scores_by_job_highest_active[$job_id]['answers']) > 0): ?>
                                    <h6 class="mb-2 text-success"><i class="bi bi-arrow-up-circle"></i> En Yüksek Puanlı <?= $limit_per_job_answers ?> Cevap</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead><tr><th>Aday</th><th>Soru</th><th>Puan</th><th>Cevap</th><th>İşlem</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($open_ended_scores_by_job_highest_active[$job_id]['answers'] as $answer): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($answer['applicant_name']) ?></td>
                                                        <td><small><?= htmlspecialchars(mb_strlen($answer['question_text']) > 30 ? mb_substr($answer['question_text'], 0, 30) . '...' : $answer['question_text']) ?></small></td>
                                                        <td><span class="score-badge score-high"><?= $answer['answer_score'] ?></span></td>
                                                        <td><small><?= htmlspecialchars(mb_strlen($answer['answer_text']) > 25 ? mb_substr($answer['answer_text'], 0, 25) . '...' : $answer['answer_text']) ?></small></td>
                                                        <td><a href="application-detail2.php?id=<?= $answer['application_id'] ?>#question-<?= $answer['question_id'] ?>" class="btn btn-xs btn-outline-info" title="Görüntüle"><i class="bi bi-eye"></i></a></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                     <p class="text-muted text-center py-2 small">Bu ilan için en yüksek puanlı açık uçlu cevap bulunamadı.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php 
                        endforeach; 
                    else: ?>
                    <div class="col-12"><div class="error-container"><div class="error-icon"><i class="bi bi-exclamation-triangle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Aktif ilanlar için iş bazında açık uçlu cevap puan verisi bulunmamaktadır.</p></div></div>
                <?php endif; ?>
            </div>
        </div>


        <!-- ============================================================================== -->
        <!-- TÜM ZAMANLAR İÇİN ANALİZLER (TÜM İLANLAR) -->
        <!-- ============================================================================== -->
        <div class="section-divider"></div>
        <div class="dashboard-container">
             <h2 class="section-title mb-3" style="font-size: 1.5rem; color: var(--secondary); border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
                <i class="bi bi-archive"></i> Tüm Zamanlar İçin Analizler (Tüm İlanlar)
            </h2>

            <!-- Genel En Düşük Puanlı Açık Uçlu Cevaplar (Tüm Zamanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-emoji-frown"></i> Genel En Düşük Puanlı Açık Uçlu Cevaplar (Tüm Zamanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($lowest_score_open_ended_all_time) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th> <th width="25%">Soru</th> <th width="15%">Aday</th>
                                        <th width="15%">İlan</th> <th width="10%">Şablon</th> <th width="5%">Puan</th>
                                        <th width="20%">Cevap</th> <th width="5%">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($lowest_score_open_ended_all_time as $index => $answer): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars(mb_strlen($answer['question_text']) > 50 ? mb_substr($answer['question_text'], 0, 50) . '...' : $answer['question_text']) ?></td>
                                            <td><?= htmlspecialchars($answer['applicant_name']) ?></td>
                                            <td><span class="metric-badge badge-primary"><?= htmlspecialchars($answer['job_title']) ?></span></td>
                                            <td><span class="metric-badge badge-info"><?= htmlspecialchars($answer['template_name']) ?></span></td>
                                            <td>
                                                <?php
                                                    $score = $answer['answer_score'];
                                                    $score_class = 'score-high'; // Default
                                                    if ($score < 40) $score_class = 'score-low';
                                                    else if ($score < 70) $score_class = 'score-medium';
                                                ?>
                                                <span class="score-badge <?= $score_class ?>"><?= $score ?></span>
                                            </td>
                                            <td><?= htmlspecialchars(mb_strlen($answer['answer_text']) > 40 ? mb_substr($answer['answer_text'], 0, 40) . '...' : $answer['answer_text']) ?></td>
                                            <td><a href="application-detail2.php?id=<?= $answer['application_id'] ?>#question-<?= $answer['question_id'] ?>" class="btn btn-sm btn-outline-info" title="Başvuruyu Görüntüle"><i class="bi bi-eye"></i></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Tüm zamanlar için düşük puanlı açık uçlu cevap bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Genel En Yüksek Puanlı Açık Uçlu Cevaplar (Tüm Zamanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-emoji-smile"></i> Genel En Yüksek Puanlı Açık Uçlu Cevaplar (Tüm Zamanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($highest_score_open_ended_all_time) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th> <th width="25%">Soru</th> <th width="15%">Aday</th>
                                        <th width="15%">İlan</th> <th width="10%">Şablon</th> <th width="5%">Puan</th>
                                        <th width="20%">Cevap</th> <th width="5%">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($highest_score_open_ended_all_time as $index => $answer): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars(mb_strlen($answer['question_text']) > 50 ? mb_substr($answer['question_text'], 0, 50) . '...' : $answer['question_text']) ?></td>
                                            <td><?= htmlspecialchars($answer['applicant_name']) ?></td>
                                            <td><span class="metric-badge badge-primary"><?= htmlspecialchars($answer['job_title']) ?></span></td>
                                            <td><span class="metric-badge badge-info"><?= htmlspecialchars($answer['template_name']) ?></span></td>
                                            <td>
                                                <?php
                                                    $score = $answer['answer_score'];
                                                    $score_class = 'score-high'; // Default
                                                    if ($score < 40) $score_class = 'score-low';
                                                    else if ($score < 70) $score_class = 'score-medium';
                                                ?>
                                                <span class="score-badge <?= $score_class ?>"><?= $score ?></span>
                                            </td>
                                            <td><?= htmlspecialchars(mb_strlen($answer['answer_text']) > 40 ? mb_substr($answer['answer_text'], 0, 40) . '...' : $answer['answer_text']) ?></td>
                                            <td><a href="application-detail2.php?id=<?= $answer['application_id'] ?>#question-<?= $answer['question_id'] ?>" class="btn btn-sm btn-outline-info" title="Başvuruyu Görüntüle"><i class="bi bi-eye"></i></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Tüm zamanlar için yüksek puanlı açık uçlu cevap bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Haftanın Günlerine Göre Başvuru Sayısı (Tüm Zamanlar) -->
            

            <!-- İş İlanına Göre Açık Uçlu Cevap Puanları (Tüm Zamanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-briefcase"></i> İş İlanlarına Göre Açık Uçlu Cevap Puanları (Tüm Zamanlar)</h3>
            <div class="row">
                 <?php 
                    $all_job_ids_all_time = array_unique(array_merge(array_keys($open_ended_scores_by_job_lowest_all_time), array_keys($open_ended_scores_by_job_highest_all_time)));
                     if (count($all_job_ids_all_time) > 0):
                        $job_titles_all_time = [];
                        foreach($open_ended_scores_by_job_lowest_all_time as $job_id => $data) $job_titles_all_time[$job_id] = $data['job_title'];
                        foreach($open_ended_scores_by_job_highest_all_time as $job_id => $data) $job_titles_all_time[$job_id] = $data['job_title'];
                        
                        foreach ($job_titles_all_time as $job_id => $job_title):
                ?>
                    <div class="col-lg-6 mb-4">
                        <div class="job-detail-card">
                            <div class="job-detail-header"><h5 class="job-detail-title"><?= htmlspecialchars($job_title) ?></h5></div>
                            <div class="job-detail-body">
                                <?php if (isset($open_ended_scores_by_job_lowest_all_time[$job_id]) && count($open_ended_scores_by_job_lowest_all_time[$job_id]['answers']) > 0): ?>
                                    <h6 class="mb-2 text-danger"><i class="bi bi-arrow-down-circle"></i> En Düşük Puanlı <?= $limit_per_job_answers ?> Cevap</h6>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm table-hover">
                                            <thead><tr><th>Aday</th><th>Soru</th><th>Puan</th><th>Cevap</th><th>İşlem</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($open_ended_scores_by_job_lowest_all_time[$job_id]['answers'] as $answer): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($answer['applicant_name']) ?></td>
                                                        <td><small><?= htmlspecialchars(mb_strlen($answer['question_text']) > 30 ? mb_substr($answer['question_text'], 0, 30) . '...' : $answer['question_text']) ?></small></td>
                                                        <td><span class="score-badge score-low"><?= $answer['answer_score'] ?></span></td>
                                                        <td><small><?= htmlspecialchars(mb_strlen($answer['answer_text']) > 25 ? mb_substr($answer['answer_text'], 0, 25) . '...' : $answer['answer_text']) ?></small></td>
                                                        <td><a href="application-detail2.php?id=<?= $answer['application_id'] ?>#question-<?= $answer['question_id'] ?>" class="btn btn-xs btn-outline-info" title="Görüntüle"><i class="bi bi-eye"></i></a></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-2 small">Bu ilan için (tüm zamanlar) en düşük puanlı açık uçlu cevap bulunamadı.</p>
                                <?php endif; ?>

                                <?php if (isset($open_ended_scores_by_job_highest_all_time[$job_id]) && count($open_ended_scores_by_job_highest_all_time[$job_id]['answers']) > 0): ?>
                                    <h6 class="mb-2 text-success"><i class="bi bi-arrow-up-circle"></i> En Yüksek Puanlı <?= $limit_per_job_answers ?> Cevap</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead><tr><th>Aday</th><th>Soru</th><th>Puan</th><th>Cevap</th><th>İşlem</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($open_ended_scores_by_job_highest_all_time[$job_id]['answers'] as $answer): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($answer['applicant_name']) ?></td>
                                                        <td><small><?= htmlspecialchars(mb_strlen($answer['question_text']) > 30 ? mb_substr($answer['question_text'], 0, 30) . '...' : $answer['question_text']) ?></small></td>
                                                        <td><span class="score-badge score-high"><?= $answer['answer_score'] ?></span></td>
                                                        <td><small><?= htmlspecialchars(mb_strlen($answer['answer_text']) > 25 ? mb_substr($answer['answer_text'], 0, 25) . '...' : $answer['answer_text']) ?></small></td>
                                                        <td><a href="application-detail2.php?id=<?= $answer['application_id'] ?>#question-<?= $answer['question_id'] ?>" class="btn btn-xs btn-outline-info" title="Görüntüle"><i class="bi bi-eye"></i></a></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                     <p class="text-muted text-center py-2 small">Bu ilan için (tüm zamanlar) en yüksek puanlı açık uçlu cevap bulunamadı.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php 
                        endforeach; 
                    else: ?>
                    <div class="col-12"><div class="error-container"><div class="error-icon"><i class="bi bi-exclamation-triangle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Tüm zamanlar için iş bazında açık uçlu cevap puan verisi bulunmamaktadır.</p></div></div>
                <?php endif; ?>
            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const chartColors = [
            'rgba(67, 97, 238, 0.7)',   // Pzt
            'rgba(16, 185, 129, 0.7)',  // Salı
            'rgba(59, 130, 246, 0.7)',  // Çar
            'rgba(245, 158, 11, 0.7)',  // Per
            'rgba(236, 72, 153, 0.7)',  // Cum
            'rgba(139, 92, 246, 0.7)',  // Cmt
            'rgba(239, 68, 68, 0.7)'    // Paz
        ];

        function createWeekdayChart(canvasId, phpData) {
            const ctx = document.getElementById(canvasId);
            if (!ctx || !phpData || phpData.length === 0) return;

            const weekdayLabels = [];
            const weekdayData = [];
            
            const sortedWeekdays = [...phpData].sort((a, b) => {
                const dayOrder = {'Pazartesi': 2, 'Salı': 3, 'Çarşamba': 4, 'Perşembe': 5, 'Cuma': 6, 'Cumartesi': 7, 'Pazar': 1};
                // Veritabanından gelen day_num MySQL standardına göre (Pazar=1, Cmt=7)
                // Grafik için Pazartesi'den Pazar'a sıralama istiyoruz.
                // day_num'ı direkt kullanabiliriz, CASE WHEN Pazar'ı 1 yaptığı için.
                // Pazartesi = 2, ..., Pazar = 1 (MySQL DAYOFWEEK)
                // Türkçe isimlere göre sıralamak için
                const orderA = dayOrder[a.day_name] === 1 ? 8 : dayOrder[a.day_name]; // Pazar en sona
                const orderB = dayOrder[b.day_name] === 1 ? 8 : dayOrder[b.day_name];
                return orderA - orderB;
            });
                
            sortedWeekdays.forEach(day => {
                weekdayLabels.push(day.day_name);
                weekdayData.push(day.application_count);
            });
            
            new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: weekdayLabels,
                    datasets: [{
                        label: 'Başvuru Sayısı',
                        data: weekdayData,
                        backgroundColor: weekdayLabels.map(label => {
                             const dayOrderMap = {'Pazartesi': 0, 'Salı': 1, 'Çarşamba': 2, 'Perşembe': 3, 'Cuma': 4, 'Cumartesi': 5, 'Pazar': 6};
                             return chartColors[dayOrderMap[label] % chartColors.length];
                        }),
                        borderWidth: 0,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: tooltipCtx => tooltipCtx.parsed.y + ' başvuru' }}},
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 }}, x: { grid: { display: false }}}
                }
            });
        }

        <?php if (count($applications_by_weekday_active) > 0): ?>
        createWeekdayChart('weekdayChartActive', <?= json_encode($applications_by_weekday_active) ?>);
        <?php endif; ?>

        <?php if (count($applications_by_weekday_all_time) > 0): ?>
        createWeekdayChart('weekdayChartAllTime', <?= json_encode($applications_by_weekday_all_time) ?>);
        <?php endif; ?>
    </script>
</body>
</html>
<?php
ob_end_flush();
?>