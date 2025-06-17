<?php
// Output buffering başlat
ob_start();

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/db.php';

// --- AKTİF İLANLAR İÇİN VERİLER ---

// 1. En çok yanlış yapılan sorular (Aktif İlanlar)
$wrong_answers_query_active = "
    SELECT 
        tq.id,
        tq.question_text,
        qt.template_name,
        qt.id as template_id,
        j.title as job_title,
        j.id as job_id,
        COUNT(aa.id) as wrong_answers,
        (
            SELECT COUNT(aa2.id)
            FROM application_answers aa2
            INNER JOIN applications app2 ON aa2.application_id = app2.id
            WHERE aa2.question_id = tq.id AND app2.job_id = j.id 
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(aa3.id) 
                FROM application_answers aa3
                INNER JOIN applications app3 ON aa3.application_id = app3.id
                WHERE aa3.question_id = tq.id AND app3.job_id = j.id
            ), 0)
        ) as error_rate
    FROM 
        template_questions tq
    JOIN 
        question_templates qt ON tq.template_id = qt.id
    JOIN 
        job_template_configs jtc ON qt.id = jtc.template_id
    JOIN 
        jobs j ON jtc.job_id = j.id AND j.status = 'active'
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    JOIN
        applications app ON aa.application_id = app.id AND app.job_id = j.id
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        tq.question_type = 'multiple_choice' 
        AND (toe.is_correct = 0 OR toe.is_correct IS NULL)
    GROUP BY 
        tq.id, tq.question_text, qt.template_name, qt.id, j.title, j.id
    ORDER BY 
        wrong_answers DESC
    LIMIT 10
";

try {
    $stmt_active = $db->query($wrong_answers_query_active);
    $wrong_questions_active = $stmt_active->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $wrong_questions_active = [];
    error_log("Error in wrong_answers_query_active: " . $e->getMessage());
}

// 1.A En çok DOĞRU yapılan sorular (Aktif İlanlar)
$correct_answers_query_active = "
    SELECT 
        tq.id,
        tq.question_text,
        qt.template_name,
        qt.id as template_id,
        j.title as job_title,
        j.id as job_id,
        COUNT(aa.id) as correct_answers,
        (
            SELECT COUNT(aa2.id)
            FROM application_answers aa2
            INNER JOIN applications app2 ON aa2.application_id = app2.id
            WHERE aa2.question_id = tq.id AND app2.job_id = j.id 
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(aa3.id) 
                FROM application_answers aa3
                INNER JOIN applications app3 ON aa3.application_id = app3.id
                WHERE aa3.question_id = tq.id AND app3.job_id = j.id
            ), 0)
        ) as success_rate
    FROM 
        template_questions tq
    JOIN 
        question_templates qt ON tq.template_id = qt.id
    JOIN 
        job_template_configs jtc ON qt.id = jtc.template_id
    JOIN 
        jobs j ON jtc.job_id = j.id AND j.status = 'active'
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    JOIN
        applications app ON aa.application_id = app.id AND app.job_id = j.id
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        tq.question_type = 'multiple_choice' 
        AND toe.is_correct = 1
    GROUP BY 
        tq.id, tq.question_text, qt.template_name, qt.id, j.title, j.id
    ORDER BY 
        correct_answers DESC
    LIMIT 10
";

try {
    $stmt_active_correct = $db->query($correct_answers_query_active);
    $correct_questions_active = $stmt_active_correct->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $correct_questions_active = [];
    error_log("Error in correct_answers_query_active: " . $e->getMessage());
}


// 2. Hangi iş ilanına başvuranlar hangi şablonda en çok yanlış yapmış (Aktif İlanlar)
$template_errors_by_job_query_active = "
    SELECT 
        j.id as job_id,
        j.title as job_title,
        qt.id as template_id,
        qt.template_name,
        COUNT(aa.id) as wrong_answers,
        (
            SELECT COUNT(aa2.id) 
            FROM application_answers aa2
            JOIN template_questions tq_sub ON aa2.question_id = tq_sub.id
            JOIN applications app_sub ON aa2.application_id = app_sub.id
            WHERE tq_sub.template_id = qt.id AND app_sub.job_id = j.id
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(aa3.id) 
                FROM application_answers aa3
                JOIN template_questions tq_sub2 ON aa3.question_id = tq_sub2.id
                JOIN applications app_sub2 ON aa3.application_id = app_sub2.id
                WHERE tq_sub2.template_id = qt.id AND app_sub2.job_id = j.id
            ), 0)
        ) as error_rate
    FROM 
        jobs j
    JOIN 
        job_template_configs jtc ON j.id = jtc.job_id
    JOIN 
        question_templates qt ON jtc.template_id = qt.id
    JOIN 
        template_questions tq ON qt.id = tq.template_id
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    JOIN 
        applications a ON aa.application_id = a.id AND a.job_id = j.id
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        (toe.is_correct = 0 OR toe.is_correct IS NULL)
        AND j.status = 'active'
        AND tq.question_type = 'multiple_choice'
    GROUP BY 
        j.id, j.title, qt.id, qt.template_name
    ORDER BY 
        j.id, wrong_answers DESC
";

try {
    $stmt_active = $db->query($template_errors_by_job_query_active);
    $template_errors_by_job_temp_active = $stmt_active->fetchAll(PDO::FETCH_ASSOC);
    
    $template_errors_by_job_active = [];
    foreach ($template_errors_by_job_temp_active as $row) {
        if (!isset($template_errors_by_job_active[$row['job_id']])) {
            $template_errors_by_job_active[$row['job_id']] = [
                'job_title' => $row['job_title'],
                'templates' => []
            ];
        }
        $template_errors_by_job_active[$row['job_id']]['templates'][] = $row;
    }
} catch (PDOException $e) {
    $template_errors_by_job_active = [];
    error_log("Error in template_errors_by_job_query_active: " . $e->getMessage());
}

// 2.A Hangi iş ilanına başvuranlar hangi şablonda en çok DOĞRU yapmış (Aktif İlanlar)
$template_success_by_job_query_active = "
    SELECT 
        j.id as job_id,
        j.title as job_title,
        qt.id as template_id,
        qt.template_name,
        COUNT(aa.id) as correct_answers,
        (
            SELECT COUNT(aa2.id) 
            FROM application_answers aa2
            JOIN template_questions tq_sub ON aa2.question_id = tq_sub.id
            JOIN applications app_sub ON aa2.application_id = app_sub.id
            WHERE tq_sub.template_id = qt.id AND app_sub.job_id = j.id
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(aa3.id) 
                FROM application_answers aa3
                JOIN template_questions tq_sub2 ON aa3.question_id = tq_sub2.id
                JOIN applications app_sub2 ON aa3.application_id = app_sub2.id
                WHERE tq_sub2.template_id = qt.id AND app_sub2.job_id = j.id
            ), 0)
        ) as success_rate
    FROM 
        jobs j
    JOIN 
        job_template_configs jtc ON j.id = jtc.job_id
    JOIN 
        question_templates qt ON jtc.template_id = qt.id
    JOIN 
        template_questions tq ON qt.id = tq.template_id
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    JOIN 
        applications a ON aa.application_id = a.id AND a.job_id = j.id
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        toe.is_correct = 1
        AND j.status = 'active'
        AND tq.question_type = 'multiple_choice'
    GROUP BY 
        j.id, j.title, qt.id, qt.template_name
    ORDER BY 
        j.id, correct_answers DESC
";
try {
    $stmt_active_succ = $db->query($template_success_by_job_query_active);
    $template_success_by_job_temp_active = $stmt_active_succ->fetchAll(PDO::FETCH_ASSOC);
    
    $template_success_by_job_active = [];
    foreach ($template_success_by_job_temp_active as $row) {
        if (!isset($template_success_by_job_active[$row['job_id']])) {
            $template_success_by_job_active[$row['job_id']] = [
                'job_title' => $row['job_title'],
                'templates' => []
            ];
        }
        $template_success_by_job_active[$row['job_id']]['templates'][] = $row;
    }
} catch (PDOException $e) {
    $template_success_by_job_active = [];
    error_log("Error in template_success_by_job_query_active: " . $e->getMessage());
}


// 3. Haftanın günlerine göre başvuru sayısı (Aktif İlanlar)
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

// 4. Şablonlara göre en çok yanlış yapılan 3 soru (Aktif İlanlar)
$wrong_questions_by_template_query_active = "
    SELECT 
        qt.id as template_id,
        qt.template_name,
        tq.id as question_id,
        tq.question_text,
        COUNT(aa.id) as wrong_answers,
        (
            SELECT COUNT(aa2.id) 
            FROM application_answers aa2
            JOIN applications app2 ON aa2.application_id = app2.id
            JOIN jobs j2 ON app2.job_id = j2.id
            WHERE aa2.question_id = tq.id AND j2.status = 'active'
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(aa3.id) 
                FROM application_answers aa3
                JOIN applications app3 ON aa3.application_id = app3.id
                JOIN jobs j3 ON app3.job_id = j3.id
                WHERE aa3.question_id = tq.id AND j3.status = 'active'
            ), 0)
        ) as error_rate
    FROM 
        question_templates qt
    JOIN 
        template_questions tq ON qt.id = tq.template_id
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    JOIN
        applications app ON aa.application_id = app.id
    JOIN
        jobs j ON app.job_id = j.id AND j.status = 'active' 
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        tq.question_type = 'multiple_choice' 
        AND (toe.is_correct = 0 OR toe.is_correct IS NULL)
    GROUP BY 
        qt.id, qt.template_name, tq.id, tq.question_text
    ORDER BY 
        qt.id, wrong_answers DESC
";
try {
    $stmt_active = $db->query($wrong_questions_by_template_query_active);
    $wrong_questions_by_template_temp_active = $stmt_active->fetchAll(PDO::FETCH_ASSOC);
    
    $wrong_questions_by_template_active = [];
    $template_question_count_active = [];
    foreach ($wrong_questions_by_template_temp_active as $row) {
        $template_id = $row['template_id'];
        if (!isset($wrong_questions_by_template_active[$template_id])) {
            $wrong_questions_by_template_active[$template_id] = ['template_name' => $row['template_name'], 'questions' => []];
            $template_question_count_active[$template_id] = 0;
        }
        if ($template_question_count_active[$template_id] < 3) {
            $wrong_questions_by_template_active[$template_id]['questions'][] = $row;
            $template_question_count_active[$template_id]++;
        }
    }
} catch (PDOException $e) {
    $wrong_questions_by_template_active = [];
    error_log("Error in wrong_questions_by_template_query_active: " . $e->getMessage());
}

// 4.A Şablonlara göre en çok DOĞRU yapılan 3 soru (Aktif İlanlar)
$correct_questions_by_template_query_active = "
    SELECT 
        qt.id as template_id,
        qt.template_name,
        tq.id as question_id,
        tq.question_text,
        COUNT(aa.id) as correct_answers,
        (
            SELECT COUNT(aa2.id) 
            FROM application_answers aa2
            JOIN applications app2 ON aa2.application_id = app2.id
            JOIN jobs j2 ON app2.job_id = j2.id
            WHERE aa2.question_id = tq.id AND j2.status = 'active'
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(aa3.id) 
                FROM application_answers aa3
                JOIN applications app3 ON aa3.application_id = app3.id
                JOIN jobs j3 ON app3.job_id = j3.id
                WHERE aa3.question_id = tq.id AND j3.status = 'active'
            ), 0)
        ) as success_rate
    FROM 
        question_templates qt
    JOIN 
        template_questions tq ON qt.id = tq.template_id
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    JOIN
        applications app ON aa.application_id = app.id
    JOIN
        jobs j ON app.job_id = j.id AND j.status = 'active' 
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        tq.question_type = 'multiple_choice' 
        AND toe.is_correct = 1
    GROUP BY 
        qt.id, qt.template_name, tq.id, tq.question_text
    ORDER BY 
        qt.id, correct_answers DESC
";
try {
    $stmt_active_corr_tpl = $db->query($correct_questions_by_template_query_active);
    $correct_questions_by_template_temp_active = $stmt_active_corr_tpl->fetchAll(PDO::FETCH_ASSOC);
    
    $correct_questions_by_template_active = [];
    $template_question_count_correct_active = [];
    foreach ($correct_questions_by_template_temp_active as $row) {
        $template_id = $row['template_id'];
        if (!isset($correct_questions_by_template_active[$template_id])) {
            $correct_questions_by_template_active[$template_id] = ['template_name' => $row['template_name'], 'questions' => []];
            $template_question_count_correct_active[$template_id] = 0;
        }
        if ($template_question_count_correct_active[$template_id] < 3) {
            $correct_questions_by_template_active[$template_id]['questions'][] = $row;
            $template_question_count_correct_active[$template_id]++;
        }
    }
} catch (PDOException $e) {
    $correct_questions_by_template_active = [];
    error_log("Error in correct_questions_by_template_query_active: " . $e->getMessage());
}


// Total applications count for active jobs
try {
    $stmt_active = $db->query("SELECT COUNT(a.id) as count FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.status = 'active'");
    $total_applications_active = $stmt_active->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $total_applications_active = 0;
    error_log("Error getting total_applications_active: " . $e->getMessage());
}


// --- TÜM ZAMANLAR İÇİN VERİLER (TÜM İLANLAR) ---

// 1. En çok yanlış yapılan sorular (Tüm Zamanlar)
$wrong_answers_query_all_time = "
    SELECT 
        tq.id,
        tq.question_text,
        qt.template_name,
        qt.id as template_id,
        j.title as job_title,
        j.id as job_id,
        COUNT(aa.id) as wrong_answers,
        (
            SELECT COUNT(*)
            FROM application_answers aa2 
            INNER JOIN applications app2 ON aa2.application_id = app2.id
            WHERE aa2.question_id = tq.id AND app2.job_id = j.id
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(*) 
                FROM application_answers aa2 
                INNER JOIN applications app2 ON aa2.application_id = app2.id
                WHERE aa2.question_id = tq.id AND app2.job_id = j.id
            ), 0)
        ) as error_rate
    FROM 
        template_questions tq
    JOIN 
        question_templates qt ON tq.template_id = qt.id
    JOIN 
        job_template_configs jtc ON qt.id = jtc.template_id
    JOIN 
        jobs j ON jtc.job_id = j.id
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    JOIN
        applications app ON aa.application_id = app.id AND app.job_id = j.id
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        tq.question_type = 'multiple_choice' 
        AND (toe.is_correct = 0 OR toe.is_correct IS NULL)
    GROUP BY 
        tq.id, tq.question_text, qt.template_name, qt.id, j.title, j.id
    ORDER BY 
        wrong_answers DESC
    LIMIT 10
";

try {
    $stmt_all_time = $db->query($wrong_answers_query_all_time);
    $wrong_questions_all_time = $stmt_all_time->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $wrong_questions_all_time = [];
    error_log("Error in wrong_answers_query_all_time: " . $e->getMessage());
}

// 1.B En çok DOĞRU yapılan sorular (Tüm Zamanlar)
$correct_answers_query_all_time = "
    SELECT 
        tq.id,
        tq.question_text,
        qt.template_name,
        qt.id as template_id,
        j.title as job_title,
        j.id as job_id,
        COUNT(aa.id) as correct_answers,
        (
            SELECT COUNT(*)
            FROM application_answers aa2 
            INNER JOIN applications app2 ON aa2.application_id = app2.id
            WHERE aa2.question_id = tq.id AND app2.job_id = j.id
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(*) 
                FROM application_answers aa2 
                INNER JOIN applications app2 ON aa2.application_id = app2.id
                WHERE aa2.question_id = tq.id AND app2.job_id = j.id
            ), 0)
        ) as success_rate
    FROM 
        template_questions tq
    JOIN 
        question_templates qt ON tq.template_id = qt.id
    JOIN 
        job_template_configs jtc ON qt.id = jtc.template_id
    JOIN 
        jobs j ON jtc.job_id = j.id
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    JOIN
        applications app ON aa.application_id = app.id AND app.job_id = j.id
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        tq.question_type = 'multiple_choice' 
        AND toe.is_correct = 1
    GROUP BY 
        tq.id, tq.question_text, qt.template_name, qt.id, j.title, j.id
    ORDER BY 
        correct_answers DESC
    LIMIT 10
";

try {
    $stmt_all_time_correct = $db->query($correct_answers_query_all_time);
    $correct_questions_all_time = $stmt_all_time_correct->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $correct_questions_all_time = [];
    error_log("Error in correct_answers_query_all_time: " . $e->getMessage());
}


// 2. Hangi iş ilanına başvuranlar hangi şablonda en çok yanlış yapmış (Tüm Zamanlar)
$template_errors_by_job_query_all_time = "
    SELECT 
        j.id as job_id,
        j.title as job_title,
        qt.id as template_id,
        qt.template_name,
        COUNT(aa.id) as wrong_answers,
        (
            SELECT COUNT(aa2.id) 
            FROM application_answers aa2
            JOIN template_questions tq_sub ON aa2.question_id = tq_sub.id
            JOIN applications app_sub ON aa2.application_id = app_sub.id
            WHERE tq_sub.template_id = qt.id AND app_sub.job_id = j.id
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(aa3.id) 
                FROM application_answers aa3
                JOIN template_questions tq_sub2 ON aa3.question_id = tq_sub2.id
                JOIN applications app_sub2 ON aa3.application_id = app_sub2.id
                WHERE tq_sub2.template_id = qt.id AND app_sub2.job_id = j.id
            ), 0)
        ) as error_rate
    FROM 
        jobs j
    JOIN 
        job_template_configs jtc ON j.id = jtc.job_id
    JOIN 
        question_templates qt ON jtc.template_id = qt.id
    JOIN 
        template_questions tq ON qt.id = tq.template_id
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    JOIN 
        applications a ON aa.application_id = a.id AND a.job_id = j.id 
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        (toe.is_correct = 0 OR toe.is_correct IS NULL)
        AND tq.question_type = 'multiple_choice'
    GROUP BY 
        j.id, j.title, qt.id, qt.template_name
    ORDER BY 
        j.id, wrong_answers DESC
";

try {
    $stmt_all_time = $db->query($template_errors_by_job_query_all_time);
    $template_errors_by_job_temp_all_time = $stmt_all_time->fetchAll(PDO::FETCH_ASSOC);
    
    $template_errors_by_job_all_time = [];
    foreach ($template_errors_by_job_temp_all_time as $row) {
        if (!isset($template_errors_by_job_all_time[$row['job_id']])) {
            $template_errors_by_job_all_time[$row['job_id']] = [
                'job_title' => $row['job_title'],
                'templates' => []
            ];
        }
        $template_errors_by_job_all_time[$row['job_id']]['templates'][] = $row;
    }
} catch (PDOException $e) {
    $template_errors_by_job_all_time = [];
    error_log("Error in template_errors_by_job_query_all_time: " . $e->getMessage());
}

// 2.B Hangi iş ilanına başvuranlar hangi şablonda en çok DOĞRU yapmış (Tüm Zamanlar)
$template_success_by_job_query_all_time = "
    SELECT 
        j.id as job_id,
        j.title as job_title,
        qt.id as template_id,
        qt.template_name,
        COUNT(aa.id) as correct_answers,
        (
            SELECT COUNT(aa2.id) 
            FROM application_answers aa2
            JOIN template_questions tq_sub ON aa2.question_id = tq_sub.id
            JOIN applications app_sub ON aa2.application_id = app_sub.id
            WHERE tq_sub.template_id = qt.id AND app_sub.job_id = j.id
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(aa3.id) 
                FROM application_answers aa3
                JOIN template_questions tq_sub2 ON aa3.question_id = tq_sub2.id
                JOIN applications app_sub2 ON aa3.application_id = app_sub2.id
                WHERE tq_sub2.template_id = qt.id AND app_sub2.job_id = j.id
            ), 0)
        ) as success_rate
    FROM 
        jobs j
    JOIN 
        job_template_configs jtc ON j.id = jtc.job_id
    JOIN 
        question_templates qt ON jtc.template_id = qt.id
    JOIN 
        template_questions tq ON qt.id = tq.template_id
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    JOIN 
        applications a ON aa.application_id = a.id AND a.job_id = j.id 
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        toe.is_correct = 1
        AND tq.question_type = 'multiple_choice'
    GROUP BY 
        j.id, j.title, qt.id, qt.template_name
    ORDER BY 
        j.id, correct_answers DESC
";
try {
    $stmt_all_time_succ = $db->query($template_success_by_job_query_all_time);
    $template_success_by_job_temp_all_time = $stmt_all_time_succ->fetchAll(PDO::FETCH_ASSOC);
    
    $template_success_by_job_all_time = [];
    foreach ($template_success_by_job_temp_all_time as $row) {
        if (!isset($template_success_by_job_all_time[$row['job_id']])) {
            $template_success_by_job_all_time[$row['job_id']] = [
                'job_title' => $row['job_title'],
                'templates' => []
            ];
        }
        $template_success_by_job_all_time[$row['job_id']]['templates'][] = $row;
    }
} catch (PDOException $e) {
    $template_success_by_job_all_time = [];
    error_log("Error in template_success_by_job_query_all_time: " . $e->getMessage());
}


// 3. Haftanın günlerine göre başvuru sayısı (Tüm Zamanlar)
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

// 4. Şablonlara göre en çok yanlış yapılan 3 soru (Tüm Zamanlar)
$wrong_questions_by_template_query_all_time = "
    SELECT 
        qt.id as template_id,
        qt.template_name,
        tq.id as question_id,
        tq.question_text,
        COUNT(aa.id) as wrong_answers,
        (
            SELECT COUNT(aa_inner.id) 
            FROM application_answers aa_inner
            WHERE aa_inner.question_id = tq.id
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(aa_inner2.id) 
                FROM application_answers aa_inner2 
                WHERE aa_inner2.question_id = tq.id
            ), 0)
        ) as error_rate
    FROM 
        question_templates qt
    JOIN 
        template_questions tq ON qt.id = tq.template_id
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        tq.question_type = 'multiple_choice' 
        AND (toe.is_correct = 0 OR toe.is_correct IS NULL)
    GROUP BY 
        qt.id, qt.template_name, tq.id, tq.question_text
    ORDER BY 
        qt.id, wrong_answers DESC 
";
// Not: Önceki sorguda ORDER BY qt.id, COUNT(aa.id) DESC idi. wrong_answers alias'ını kullanmak daha okunaklı.
try {
    $stmt_all_time = $db->query($wrong_questions_by_template_query_all_time);
    $wrong_questions_by_template_temp_all_time = $stmt_all_time->fetchAll(PDO::FETCH_ASSOC);
    
    $wrong_questions_by_template_all_time = [];
    $template_question_count_all_time = [];
    foreach ($wrong_questions_by_template_temp_all_time as $row) {
        $template_id = $row['template_id'];
        if (!isset($wrong_questions_by_template_all_time[$template_id])) {
            $wrong_questions_by_template_all_time[$template_id] = ['template_name' => $row['template_name'], 'questions' => []];
            $template_question_count_all_time[$template_id] = 0;
        }
        if ($template_question_count_all_time[$template_id] < 3) {
            $wrong_questions_by_template_all_time[$template_id]['questions'][] = $row;
            $template_question_count_all_time[$template_id]++;
        }
    }
} catch (PDOException $e) {
    $wrong_questions_by_template_all_time = [];
    error_log("Error in wrong_questions_by_template_query_all_time: " . $e->getMessage());
}

// 4.B Şablonlara göre en çok DOĞRU yapılan 3 soru (Tüm Zamanlar)
$correct_questions_by_template_query_all_time = "
    SELECT 
        qt.id as template_id,
        qt.template_name,
        tq.id as question_id,
        tq.question_text,
        COUNT(aa.id) as correct_answers,
        (
            SELECT COUNT(aa_inner.id) 
            FROM application_answers aa_inner
            WHERE aa_inner.question_id = tq.id
        ) as total_answers,
        ROUND(
            (COUNT(aa.id) * 100.0) / 
            NULLIF((
                SELECT COUNT(aa_inner2.id) 
                FROM application_answers aa_inner2
                WHERE aa_inner2.question_id = tq.id
            ), 0)
        ) as success_rate
    FROM 
        question_templates qt
    JOIN 
        template_questions tq ON qt.id = tq.template_id
    JOIN 
        application_answers aa ON tq.id = aa.question_id
    LEFT JOIN 
        template_options toe ON aa.option_id = toe.id
    WHERE 
        tq.question_type = 'multiple_choice' 
        AND toe.is_correct = 1
    GROUP BY 
        qt.id, qt.template_name, tq.id, tq.question_text
    ORDER BY 
        qt.id, correct_answers DESC
";
try {
    $stmt_all_time_corr_tpl = $db->query($correct_questions_by_template_query_all_time);
    $correct_questions_by_template_temp_all_time = $stmt_all_time_corr_tpl->fetchAll(PDO::FETCH_ASSOC);
    
    $correct_questions_by_template_all_time = [];
    $template_question_count_correct_all_time = [];
    foreach ($correct_questions_by_template_temp_all_time as $row) {
        $template_id = $row['template_id'];
        if (!isset($correct_questions_by_template_all_time[$template_id])) {
            $correct_questions_by_template_all_time[$template_id] = ['template_name' => $row['template_name'], 'questions' => []];
            $template_question_count_correct_all_time[$template_id] = 0;
        }
        if ($template_question_count_correct_all_time[$template_id] < 3) {
            $correct_questions_by_template_all_time[$template_id]['questions'][] = $row;
            $template_question_count_correct_all_time[$template_id]++;
        }
    }
} catch (PDOException $e) {
    $correct_questions_by_template_all_time = [];
    error_log("Error in correct_questions_by_template_query_all_time: " . $e->getMessage());
}

// Total applications count for all time
try {
    $stmt_all_time = $db->query("SELECT COUNT(*) as count FROM applications");
    $total_applications_all_time = $stmt_all_time->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $total_applications_all_time = 0;
    error_log("Error getting total_applications_all_time: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Başvuru Analizi | İş Başvuru Sistemi</title>
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
        }
        .job-detail-header.success { background-color: var(--success-light); }
        .job-detail-header.danger { background-color: var(--primary-light); } /* Primary for "wrong" */
        
        .job-detail-title {
            font-weight: 600;
            margin-bottom: 0;
        }
        .job-detail-title.success { color: var(--success); }
        .job-detail-title.danger { color: var(--primary); } /* Primary for "wrong" */
        
        .job-detail-body { padding: 1.25rem; }
        
        .template-card {
            background-color: var(--card-bg);
            border-radius: 0.75rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 1.5rem;
            height: 100%;
        }
        
        .template-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .template-header.info { background-color: var(--info-light); } /* For "wrong" questions in template */
        .template-header.success { background-color: var(--success-light); } /* For "correct" questions in template */
        
        .template-title {
            font-weight: 600;
            margin-bottom: 0;
            display: flex;
            align-items: center;
        }
        .template-title.info { color: var(--info); }
        .template-title.success { color: var(--success); }

        .template-title i { margin-right: 0.5rem; }
        .template-body { padding: 1rem; }
        
        .question-item {
            padding: 0.75rem;
            border-radius: 0.5rem;
            background-color: var(--light);
            margin-bottom: 0.75rem;
        }
        .question-item:last-child { margin-bottom: 0; }
        .question-text { font-size: 0.875rem; margin-bottom: 0.5rem; }
        .question-stats { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-secondary); }
        
        .rate-badge { font-weight: 600; }
        .rate-high { color: var(--danger); } /* For high error or low success */
        .rate-medium { color: var(--warning); } /* For medium error/success */
        .rate-low { color: var(--success); } /* For low error or high success */
        
        .error-container {
            text-align: center; padding: 2rem; background-color: var(--light);
            border-radius: 0.75rem; margin-bottom: 1.5rem;
        }
        .error-icon { font-size: 3rem; color: var(--warning); margin-bottom: 1rem; }
        .error-title { font-weight: 600; margin-bottom: 0.5rem; font-size: 1.25rem; }
        .error-message { color: var(--text-secondary); margin-bottom: 1.5rem; }
        
        @media (max-width: 991.98px) {
            .admin-navbar .navbar-nav { padding-top: 15px; }
            .admin-navbar .nav-link { padding: 10px; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="page-title">Başvuru Analizi</h1>
            <p class="page-subtitle">Detaylı şablon ve soru performans analizi (Aktif İlanlar ve Tüm Zamanlar)</p>
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

            <!-- En Çok Yanlış Yapılan Sorular (Aktif İlanlar) -->
            <h3 class="section-title"><i class="bi bi-exclamation-circle" style="color: var(--danger);"></i> En Çok Yanlış Yapılan Sorular (Aktif İlanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($wrong_questions_active) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th><th width="35%">Soru</th><th width="15%">İlan</th>
                                        <th width="15%">Şablon</th><th width="15%">Yanlış Sayısı / Toplam</th>
                                        <th width="10%">Hata Oranı</th><th width="5%">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($wrong_questions_active as $index => $question): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars(mb_strlen($question['question_text']) > 60 ? mb_substr($question['question_text'], 0, 60) . '...' : $question['question_text']) ?></td>
                                            <td><span class="metric-badge badge-primary"><?= htmlspecialchars($question['job_title']) ?></span></td>
                                            <td><span class="metric-badge badge-info"><?= htmlspecialchars($question['template_name']) ?></span></td>
                                            <td>
                                                <span class="metric-badge badge-danger"><?= $question['wrong_answers'] ?></span>
                                                <small class="text-muted">/ <?= $question['total_answers'] ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                    $error_rate = $question['error_rate'] ?? 0;
                                                    $rate_class_bg = 'bg-success';
                                                    if($error_rate > 50) $rate_class_bg = 'bg-danger';
                                                    else if($error_rate > 25) $rate_class_bg = 'bg-warning';
                                                ?>
                                                <div class="progress" style="height: 8px; width: 100px;">
                                                    <div class="progress-bar <?= $rate_class_bg ?>" role="progressbar" style="width: <?= $error_rate ?>%" aria-valuenow="<?= $error_rate ?>"></div>
                                                </div>
                                                <small><?= $error_rate ?>%</small>
                                            </td>
                                            <td><a href="edit-template-question.php?id=<?= $question['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Aktif ilanlar için yanlış cevaplanan soru bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- En Çok DOĞRU Yapılan Sorular (Aktif İlanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-check-circle" style="color: var(--success);"></i> En Çok Doğru Yapılan Sorular (Aktif İlanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($correct_questions_active) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th><th width="35%">Soru</th><th width="15%">İlan</th>
                                        <th width="15%">Şablon</th><th width="15%">Doğru Sayısı / Toplam</th>
                                        <th width="10%">Başarı Oranı</th><th width="5%">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($correct_questions_active as $index => $question): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars(mb_strlen($question['question_text']) > 60 ? mb_substr($question['question_text'], 0, 60) . '...' : $question['question_text']) ?></td>
                                            <td><span class="metric-badge badge-primary"><?= htmlspecialchars($question['job_title']) ?></span></td>
                                            <td><span class="metric-badge badge-info"><?= htmlspecialchars($question['template_name']) ?></span></td>
                                            <td>
                                                <span class="metric-badge badge-success"><?= $question['correct_answers'] ?></span>
                                                <small class="text-muted">/ <?= $question['total_answers'] ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                    $success_rate = $question['success_rate'] ?? 0;
                                                    $rate_class_bg = 'bg-danger';
                                                    if($success_rate > 75) $rate_class_bg = 'bg-success';
                                                    else if($success_rate > 50) $rate_class_bg = 'bg-primary'; // Or some other positive indicator
                                                ?>
                                                <div class="progress" style="height: 8px; width: 100px;">
                                                    <div class="progress-bar <?= $rate_class_bg ?>" role="progressbar" style="width: <?= $success_rate ?>%" aria-valuenow="<?= $success_rate ?>"></div>
                                                </div>
                                                <small><?= $success_rate ?>%</small>
                                            </td>
                                            <td><a href="edit-template-question.php?id=<?= $question['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Aktif ilanlar için doğru cevaplanan soru bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>


            <!-- Haftanın Günlerine Göre Başvuru Sayısı (Aktif İlanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-calendar-week" style="color: var(--primary);"></i> Haftanın Günlerine Göre Başvuru Sayısı (Aktif İlanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($applications_by_weekday_active) > 0): ?>
                        <div class="row">
                            <div class="col-lg-8"><div class="chart-container"><canvas id="weekdayChartActive"></canvas></div></div>
                            <div class="col-lg-4">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>Gün</th><th>Başvuru Sayısı</th><th>Oran</th></tr></thead>
                                        <tbody>
                                            <?php 
                                            $total_daily_apps_active = array_sum(array_column($applications_by_weekday_active, 'application_count'));
                                            foreach($applications_by_weekday_active as $day): ?>
                                                <?php $percentage = $total_daily_apps_active > 0 ? round(($day['application_count'] / $total_daily_apps_active) * 100, 1) : 0; ?>
                                                <tr>
                                                    <td><?= $day['day_name'] ?></td>
                                                    <td><?= $day['application_count'] ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 8px; width: 80px;"><div class="progress-bar bg-primary" style="width: <?= $percentage ?>%"></div></div>
                                                        <small><?= $percentage ?>%</small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Aktif ilanlar için haftalık başvuru verisi bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- İlanlar ve Şablonlarda Yapılan Hatalar (Aktif İlanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-clipboard-x" style="color: var(--danger);"></i> İlanlar ve Şablonlarda Yapılan Hatalar (Aktif İlanlar)</h3>
            <div class="row">
                <?php if (count($template_errors_by_job_active) > 0): ?>
                    <?php foreach ($template_errors_by_job_active as $job_id => $job_data): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="job-detail-card">
                                <div class="job-detail-header danger"><h5 class="job-detail-title danger"><?= htmlspecialchars($job_data['job_title']) ?></h5></div>
                                <div class="job-detail-body">
                                    <?php if (!empty($job_data['templates'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead><tr><th>Şablon</th><th>Yanlış / Toplam</th><th>Hata Oranı</th></tr></thead>
                                                <tbody>
                                                    <?php foreach ($job_data['templates'] as $template): ?>
                                                        <tr>
                                                            <td><span class="metric-badge badge-info"><i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars($template['template_name']) ?></span></td>
                                                            <td>
                                                                <span class="metric-badge badge-danger"><?= $template['wrong_answers'] ?></span>
                                                                <small class="text-muted">/ <?= $template['total_answers'] ?></small>
                                                            </td>
                                                            <td>
                                                                <?php 
                                                                    $error_rate = $template['error_rate'] ?? 0;
                                                                    $rate_class_bg = 'bg-success';
                                                                    if($error_rate > 50) $rate_class_bg = 'bg-danger';
                                                                    else if($error_rate > 25) $rate_class_bg = 'bg-warning';
                                                                ?>
                                                                <div class="progress" style="height: 8px; width: 80px;"><div class="progress-bar <?= $rate_class_bg ?>" style="width: <?= $error_rate ?>%"></div></div>
                                                                <small><?= $error_rate ?>%</small>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-3">Bu aktif ilan için şablon hata verisi bulunmamaktadır.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Aktif ilanlar için şablon hata verisi bulunmamaktadır.</p></div></div>
                <?php endif; ?>
            </div>

            <!-- İlanlar ve Şablonlarda Başarı (Aktif İlanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-clipboard-check" style="color: var(--success);"></i> İlanlar ve Şablonlarda Başarı Oranları (Aktif İlanlar)</h3>
            <div class="row">
                <?php if (count($template_success_by_job_active) > 0): ?>
                    <?php foreach ($template_success_by_job_active as $job_id => $job_data): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="job-detail-card">
                                <div class="job-detail-header success"><h5 class="job-detail-title success"><?= htmlspecialchars($job_data['job_title']) ?></h5></div>
                                <div class="job-detail-body">
                                    <?php if (!empty($job_data['templates'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead><tr><th>Şablon</th><th>Doğru / Toplam</th><th>Başarı Oranı</th></tr></thead>
                                                <tbody>
                                                    <?php foreach ($job_data['templates'] as $template): ?>
                                                        <tr>
                                                            <td><span class="metric-badge badge-info"><i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars($template['template_name']) ?></span></td>
                                                            <td>
                                                                <span class="metric-badge badge-success"><?= $template['correct_answers'] ?></span>
                                                                <small class="text-muted">/ <?= $template['total_answers'] ?></small>
                                                            </td>
                                                            <td>
                                                                <?php 
                                                                    $success_rate = $template['success_rate'] ?? 0;
                                                                    $rate_class_bg = 'bg-danger';
                                                                    if($success_rate > 75) $rate_class_bg = 'bg-success';
                                                                    else if($success_rate > 50) $rate_class_bg = 'bg-primary';
                                                                ?>
                                                                <div class="progress" style="height: 8px; width: 80px;"><div class="progress-bar <?= $rate_class_bg ?>" style="width: <?= $success_rate ?>%"></div></div>
                                                                <small><?= $success_rate ?>%</small>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-3">Bu aktif ilan için şablon başarı verisi bulunmamaktadır.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Aktif ilanlar için şablon başarı verisi bulunmamaktadır.</p></div></div>
                <?php endif; ?>
            </div>


            <!-- Şablonlara Göre En Çok Yanlış Yapılan Sorular (Aktif İlanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-question-diamond" style="color: var(--danger);"></i> Şablonlara Göre En Çok Yanlış Yapılan Sorular (Aktif İlanlar)</h3>
            <div class="row">
                <?php if (count($wrong_questions_by_template_active) > 0): ?>
                    <?php foreach ($wrong_questions_by_template_active as $template_id => $template_data): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="template-card">
                                <div class="template-header info"><h5 class="template-title info"><i class="bi bi-file-earmark-text"></i><?= htmlspecialchars($template_data['template_name']) ?></h5></div>
                                <div class="template-body">
                                    <?php if (!empty($template_data['questions'])): ?>
                                        <?php foreach ($template_data['questions'] as $question): ?>
                                            <div class="question-item">
                                                <div class="question-text"><?= htmlspecialchars(mb_strlen($question['question_text']) > 70 ? mb_substr($question['question_text'], 0, 70) . '...' : $question['question_text']) ?></div>
                                                <div class="question-stats">
                                                    <span><i class="bi bi-x-circle text-danger me-1"></i><?= $question['wrong_answers'] ?> / <?= $question['total_answers'] ?> yanlış</span>
                                                    <?php
                                                        $error_rate = $question['error_rate'] ?? 0;
                                                        $rate_class_text = 'rate-low'; // Düşük hata iyi
                                                        if($error_rate > 50) $rate_class_text = 'rate-high'; 
                                                        else if($error_rate > 25) $rate_class_text = 'rate-medium';
                                                    ?>
                                                    <span class="rate-badge <?= $rate_class_text ?>"><?= $error_rate ?>% hata</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-3">Bu şablon için (aktif ilanlar) yanlış soru verisi bulunmamaktadır.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Aktif ilanlar kapsamında şablonlara göre yanlış soru verisi bulunmamaktadır.</p></div></div>
                <?php endif; ?>
            </div>

            <!-- Şablonlara Göre En Çok DOĞRU Yapılan Sorular (Aktif İlanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-patch-check" style="color: var(--success);"></i> Şablonlara Göre En Çok Doğru Yapılan Sorular (Aktif İlanlar)</h3>
            <div class="row">
                <?php if (count($correct_questions_by_template_active) > 0): ?>
                    <?php foreach ($correct_questions_by_template_active as $template_id => $template_data): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="template-card">
                                <div class="template-header success"><h5 class="template-title success"><i class="bi bi-file-earmark-text"></i><?= htmlspecialchars($template_data['template_name']) ?></h5></div>
                                <div class="template-body">
                                    <?php if (!empty($template_data['questions'])): ?>
                                        <?php foreach ($template_data['questions'] as $question): ?>
                                            <div class="question-item">
                                                <div class="question-text"><?= htmlspecialchars(mb_strlen($question['question_text']) > 70 ? mb_substr($question['question_text'], 0, 70) . '...' : $question['question_text']) ?></div>
                                                <div class="question-stats">
                                                    <span><i class="bi bi-check-circle text-success me-1"></i><?= $question['correct_answers'] ?> / <?= $question['total_answers'] ?> doğru</span>
                                                    <?php
                                                        $success_rate = $question['success_rate'] ?? 0;
                                                        $rate_class_text = 'rate-high'; // Yüksek başarı iyi
                                                        if($success_rate < 50) $rate_class_text = 'rate-high'; // Kırmızı gibi (aslında düşük başarı)
                                                        else if($success_rate < 75) $rate_class_text = 'rate-medium';
                                                    ?>
                                                    <span class="rate-badge <?= $success_rate > 75 ? 'rate-low' : ($success_rate > 50 ? 'rate-medium' : 'rate-high') // rate-low green, rate-high red olacak şekilde ayarlandı ?>"><?= $success_rate ?>% başarı</span>

                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-3">Bu şablon için (aktif ilanlar) doğru soru verisi bulunmamaktadır.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Aktif ilanlar kapsamında şablonlara göre doğru soru verisi bulunmamaktadır.</p></div></div>
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

            <!-- En Çok Yanlış Yapılan Sorular (Tüm Zamanlar) -->
            <h3 class="section-title"><i class="bi bi-exclamation-circle" style="color: var(--danger);"></i> En Çok Yanlış Yapılan Sorular (Tüm Zamanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($wrong_questions_all_time) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                     <tr>
                                        <th width="5%">#</th><th width="35%">Soru</th><th width="15%">İlan</th>
                                        <th width="15%">Şablon</th><th width="15%">Yanlış Sayısı / Toplam</th>
                                        <th width="10%">Hata Oranı</th><th width="5%">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($wrong_questions_all_time as $index => $question): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars(mb_strlen($question['question_text']) > 60 ? mb_substr($question['question_text'], 0, 60) . '...' : $question['question_text']) ?></td>
                                            <td><span class="metric-badge badge-primary"><?= htmlspecialchars($question['job_title']) ?></span></td>
                                            <td><span class="metric-badge badge-info"><?= htmlspecialchars($question['template_name']) ?></span></td>
                                            <td>
                                                <span class="metric-badge badge-danger"><?= $question['wrong_answers'] ?></span>
                                                <small class="text-muted">/ <?= $question['total_answers'] ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                    $error_rate = $question['error_rate'] ?? 0;
                                                    $rate_class_bg = 'bg-success';
                                                    if($error_rate > 50) $rate_class_bg = 'bg-danger';
                                                    else if($error_rate > 25) $rate_class_bg = 'bg-warning';
                                                ?>
                                                <div class="progress" style="height: 8px; width: 100px;">
                                                    <div class="progress-bar <?= $rate_class_bg ?>" role="progressbar" style="width: <?= $error_rate ?>%" aria-valuenow="<?= $error_rate ?>"></div>
                                                </div>
                                                <small><?= $error_rate ?>%</small>
                                            </td>
                                            <td><a href="edit-template-question.php?id=<?= $question['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                         <div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Tüm zamanlar için yanlış cevaplanan soru bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- En Çok DOĞRU Yapılan Sorular (Tüm Zamanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-check-circle" style="color: var(--success);"></i> En Çok Doğru Yapılan Sorular (Tüm Zamanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($correct_questions_all_time) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th><th width="35%">Soru</th><th width="15%">İlan</th>
                                        <th width="15%">Şablon</th><th width="15%">Doğru Sayısı / Toplam</th>
                                        <th width="10%">Başarı Oranı</th><th width="5%">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($correct_questions_all_time as $index => $question): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars(mb_strlen($question['question_text']) > 60 ? mb_substr($question['question_text'], 0, 60) . '...' : $question['question_text']) ?></td>
                                            <td><span class="metric-badge badge-primary"><?= htmlspecialchars($question['job_title']) ?></span></td>
                                            <td><span class="metric-badge badge-info"><?= htmlspecialchars($question['template_name']) ?></span></td>
                                            <td>
                                                <span class="metric-badge badge-success"><?= $question['correct_answers'] ?></span>
                                                <small class="text-muted">/ <?= $question['total_answers'] ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                    $success_rate = $question['success_rate'] ?? 0;
                                                    $rate_class_bg = 'bg-danger';
                                                    if($success_rate > 75) $rate_class_bg = 'bg-success';
                                                    else if($success_rate > 50) $rate_class_bg = 'bg-primary';
                                                ?>
                                                <div class="progress" style="height: 8px; width: 100px;">
                                                    <div class="progress-bar <?= $rate_class_bg ?>" role="progressbar" style="width: <?= $success_rate ?>%" aria-valuenow="<?= $success_rate ?>"></div>
                                                </div>
                                                <small><?= $success_rate ?>%</small>
                                            </td>
                                            <td><a href="edit-template-question.php?id=<?= $question['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                         <div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Tüm zamanlar için doğru cevaplanan soru bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Haftanın Günlerine Göre Başvuru Sayısı (Tüm Zamanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-calendar-week" style="color: var(--primary);"></i> Haftanın Günlerine Göre Başvuru Sayısı (Tüm Zamanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($applications_by_weekday_all_time) > 0): ?>
                        <div class="row">
                            <div class="col-lg-8"><div class="chart-container"><canvas id="weekdayChartAllTime"></canvas></div></div>
                            <div class="col-lg-4">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>Gün</th><th>Başvuru Sayısı</th><th>Oran</th></tr></thead>
                                        <tbody>
                                            <?php 
                                            $total_daily_apps_all_time = array_sum(array_column($applications_by_weekday_all_time, 'application_count'));
                                            foreach($applications_by_weekday_all_time as $day): ?>
                                                <?php $percentage = $total_daily_apps_all_time > 0 ? round(($day['application_count'] / $total_daily_apps_all_time) * 100, 1) : 0; ?>
                                                <tr>
                                                    <td><?= $day['day_name'] ?></td>
                                                    <td><?= $day['application_count'] ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 8px; width: 80px;"><div class="progress-bar bg-primary" style="width: <?= $percentage ?>%"></div></div>
                                                        <small><?= $percentage ?>%</small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Tüm zamanlar için haftalık başvuru verisi bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- İş İlanına Göre Şablonlarda Yapılan Hatalar (Tüm Zamanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-clipboard-x" style="color: var(--danger);"></i> İlanlar ve Şablonlarda Yapılan Hatalar (Tüm Zamanlar)</h3>
            <div class="row">
                <?php if (count($template_errors_by_job_all_time) > 0): ?>
                    <?php foreach ($template_errors_by_job_all_time as $job_id => $job_data): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="job-detail-card">
                                <div class="job-detail-header danger"><h5 class="job-detail-title danger"><?= htmlspecialchars($job_data['job_title']) ?></h5></div>
                                <div class="job-detail-body">
                                    <?php if (!empty($job_data['templates'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead><tr><th>Şablon</th><th>Yanlış / Toplam</th><th>Hata Oranı</th></tr></thead>
                                                <tbody>
                                                    <?php foreach ($job_data['templates'] as $template): ?>
                                                        <tr>
                                                            <td><span class="metric-badge badge-info"><i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars($template['template_name']) ?></span></td>
                                                            <td>
                                                                <span class="metric-badge badge-danger"><?= $template['wrong_answers'] ?></span>
                                                                <small class="text-muted">/ <?= $template['total_answers'] ?></small>
                                                            </td>
                                                            <td>
                                                                <?php 
                                                                    $error_rate = $template['error_rate'] ?? 0;
                                                                    $rate_class_bg = 'bg-success';
                                                                    if($error_rate > 50) $rate_class_bg = 'bg-danger';
                                                                    else if($error_rate > 25) $rate_class_bg = 'bg-warning';
                                                                ?>
                                                                <div class="progress" style="height: 8px; width: 80px;"><div class="progress-bar <?= $rate_class_bg ?>" style="width: <?= $error_rate ?>%"></div></div>
                                                                <small><?= $error_rate ?>%</small>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                         <p class="text-muted text-center py-3">Bu ilan için (tüm zamanlar) şablon hata verisi bulunmamaktadır.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Tüm zamanlar için şablon hata verisi bulunmamaktadır.</p></div></div>
                <?php endif; ?>
            </div>

             <!-- İş İlanına Göre Şablonlarda Başarı (Tüm Zamanlar) -->
            <h3 class="section-title mt-4"><i class="bi bi-clipboard-check" style="color: var(--success);"></i> İlanlar ve Şablonlarda Başarı Oranları (Tüm Zamanlar)</h3>
            <div class="row">
                <?php if (count($template_success_by_job_all_time) > 0): ?>
                    <?php foreach ($template_success_by_job_all_time as $job_id => $job_data): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="job-detail-card">
                                <div class="job-detail-header success"><h5 class="job-detail-title success"><?= htmlspecialchars($job_data['job_title']) ?></h5></div>
                                <div class="job-detail-body">
                                    <?php if (!empty($job_data['templates'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead><tr><th>Şablon</th><th>Doğru / Toplam</th><th>Başarı Oranı</th></tr></thead>
                                                <tbody>
                                                    <?php foreach ($job_data['templates'] as $template): ?>
                                                        <tr>
                                                            <td><span class="metric-badge badge-info"><i class="bi bi-file-earmark-text me-1"></i><?= htmlspecialchars($template['template_name']) ?></span></td>
                                                            <td>
                                                                <span class="metric-badge badge-success"><?= $template['correct_answers'] ?></span>
                                                                <small class="text-muted">/ <?= $template['total_answers'] ?></small>
                                                            </td>
                                                            <td>
                                                                <?php 
                                                                    $success_rate = $template['success_rate'] ?? 0;
                                                                    $rate_class_bg = 'bg-danger';
                                                                    if($success_rate > 75) $rate_class_bg = 'bg-success';
                                                                    else if($success_rate > 50) $rate_class_bg = 'bg-primary';
                                                                ?>
                                                                <div class="progress" style="height: 8px; width: 80px;"><div class="progress-bar <?= $rate_class_bg ?>" style="width: <?= $success_rate ?>%"></div></div>
                                                                <small><?= $success_rate ?>%</small>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                         <p class="text-muted text-center py-3">Bu ilan için (tüm zamanlar) şablon başarı verisi bulunmamaktadır.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Tüm zamanlar için şablon başarı verisi bulunmamaktadır.</p></div></div>
                <?php endif; ?>
            </div>


            <!-- Şablonlara Göre En Çok Yanlış Yapılan Sorular (Tüm Zamanlar) -->
             <h3 class="section-title mt-4"><i class="bi bi-question-diamond" style="color: var(--danger);"></i> Şablonlara Göre En Çok Yanlış Yapılan Sorular (Tüm Zamanlar)</h3>
            <div class="row">
                <?php if (count($wrong_questions_by_template_all_time) > 0): ?>
                    <?php foreach ($wrong_questions_by_template_all_time as $template_id => $template_data): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="template-card">
                                <div class="template-header info"><h5 class="template-title info"><i class="bi bi-file-earmark-text"></i><?= htmlspecialchars($template_data['template_name']) ?></h5></div>
                                <div class="template-body">
                                    <?php if (!empty($template_data['questions'])): ?>
                                        <?php foreach ($template_data['questions'] as $question): ?>
                                            <div class="question-item">
                                                <div class="question-text"><?= htmlspecialchars(mb_strlen($question['question_text']) > 70 ? mb_substr($question['question_text'], 0, 70) . '...' : $question['question_text']) ?></div>
                                                <div class="question-stats">
                                                    <span><i class="bi bi-x-circle text-danger me-1"></i><?= $question['wrong_answers'] ?> / <?= $question['total_answers'] ?> yanlış</span>
                                                     <?php
                                                        $error_rate = $question['error_rate'] ?? 0;
                                                        $rate_class_text = 'rate-low'; 
                                                        if($error_rate > 50) $rate_class_text = 'rate-high'; 
                                                        else if($error_rate > 25) $rate_class_text = 'rate-medium';
                                                    ?>
                                                    <span class="rate-badge <?= $rate_class_text ?>"><?= $error_rate ?>% hata</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-3">Bu şablon için (tüm zamanlar) yanlış soru verisi bulunmamaktadır.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Tüm zamanlar için şablonlara göre yanlış soru verisi bulunmamaktadır.</p></div></div>
                <?php endif; ?>
            </div>

            <!-- Şablonlara Göre En Çok DOĞRU Yapılan Sorular (Tüm Zamanlar) -->
             <h3 class="section-title mt-4"><i class="bi bi-patch-check" style="color: var(--success);"></i> Şablonlara Göre En Çok Doğru Yapılan Sorular (Tüm Zamanlar)</h3>
            <div class="row">
                <?php if (count($correct_questions_by_template_all_time) > 0): ?>
                    <?php foreach ($correct_questions_by_template_all_time as $template_id => $template_data): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="template-card">
                                <div class="template-header success"><h5 class="template-title success"><i class="bi bi-file-earmark-text"></i><?= htmlspecialchars($template_data['template_name']) ?></h5></div>
                                <div class="template-body">
                                    <?php if (!empty($template_data['questions'])): ?>
                                        <?php foreach ($template_data['questions'] as $question): ?>
                                            <div class="question-item">
                                                <div class="question-text"><?= htmlspecialchars(mb_strlen($question['question_text']) > 70 ? mb_substr($question['question_text'], 0, 70) . '...' : $question['question_text']) ?></div>
                                                <div class="question-stats">
                                                    <span><i class="bi bi-check-circle text-success me-1"></i><?= $question['correct_answers'] ?> / <?= $question['total_answers'] ?> doğru</span>
                                                     <?php
                                                        $success_rate = $question['success_rate'] ?? 0;
                                                     ?>
                                                     <span class="rate-badge <?= $success_rate > 75 ? 'rate-low' : ($success_rate > 50 ? 'rate-medium' : 'rate-high') ?>"><?= $success_rate ?>% başarı</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-3">Bu şablon için (tüm zamanlar) doğru soru verisi bulunmamaktadır.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><div class="error-container"><div class="error-icon"><i class="bi bi-info-circle"></i></div><h5 class="error-title">Veri Bulunamadı</h5><p class="error-message">Tüm zamanlar için şablonlara göre doğru soru verisi bulunmamaktadır.</p></div></div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const chartColors = [
            'rgba(67, 97, 238, 0.7)',   // Pazartesi (Primary)
            'rgba(16, 185, 129, 0.7)',  // Salı (Success)
            'rgba(59, 130, 246, 0.7)',  // Çarşamba (Info)
            'rgba(245, 158, 11, 0.7)',  // Perşembe (Warning)
            'rgba(236, 72, 153, 0.7)',  // Cuma (Pink-ish)
            'rgba(139, 92, 246, 0.7)',  // Cumartesi (Purple-ish)
            'rgba(107, 114, 128, 0.7)'    // Pazar (Gray)
        ];

        function createWeekdayChart(canvasId, phpData) {
            const ctx = document.getElementById(canvasId);
            if (!ctx || !phpData || phpData.length === 0) {
                 if(ctx) { // Canvas var ama veri yoksa boş mesaj göster
                    const parent = ctx.parentNode;
                    parent.innerHTML = '<div class="error-container" style="padding:1rem; margin:0;"><div class="error-icon" style="font-size:2rem; margin-bottom:0.5rem;"><i class="bi bi-bar-chart-line"></i></div><p class="error-message" style="margin-bottom:0;">Bu periyot için grafik verisi bulunamadı.</p></div>';
                }
                return;
            }

            const weekdayLabels = [];
            const weekdayData = [];
            
            // MySQL DAYOFWEEK: 1=Pazar, 2=Pazartesi, ..., 7=Cumartesi
            // Grafikte Pazartesi'den Pazar'a sıralamak için:
            const dayOrder = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
            
            const dataMap = new Map();
            phpData.forEach(day => {
                dataMap.set(day.day_name, day.application_count);
            });

            dayOrder.forEach(dayName => {
                weekdayLabels.push(dayName);
                weekdayData.push(dataMap.get(dayName) || 0); // Eğer o gün veri yoksa 0 ata
            });
            
            new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: weekdayLabels,
                    datasets: [{
                        label: 'Başvuru Sayısı',
                        data: weekdayData,
                        backgroundColor: chartColors.slice(0, weekdayLabels.length),
                        borderWidth: 0,
                        borderRadius: 6,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false }, 
                        tooltip: { 
                            backgroundColor: 'rgba(0,0,0,0.7)',
                            titleFont: { weight: 'bold' },
                            bodyFont: { size: 13 },
                            callbacks: { label: ctx => ' ' + ctx.parsed.y + ' başvuru' }
                        }
                    },
                    scales: { 
                        y: { 
                            beginAtZero: true, 
                            ticks: { precision: 0, color: '#64748b' },
                            grid: { color: '#e2e8f0' }
                        }, 
                        x: { 
                            grid: { display: false },
                            ticks: { color: '#64748b' }
                        }
                    }
                }
            });
        }

        // Aktif İlanlar için Haftalık Grafik
        createWeekdayChart('weekdayChartActive', <?= json_encode($applications_by_weekday_active) ?>);
        

        // Tüm Zamanlar için Haftalık Grafik
        createWeekdayChart('weekdayChartAllTime', <?= json_encode($applications_by_weekday_all_time) ?>);
        
    </script>
</body>
</html>
<?php
ob_end_flush();
?>