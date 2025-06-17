<?php
// Output buffering başlat
ob_start();

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/db.php'; // Bu dosyanın PDO $db nesnesini oluşturduğunu varsayıyoruz

// Current user and time information
$current_user = $_SESSION['admin_id'] ?? 'Unknown';
$current_date_time = date('Y-m-d H:i:s');

$months_tr = ["", "Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];
$current_date_display = date('d') . ' ' . $months_tr[date('n')] . ' ' . date('Y');


// 1. İş ilanına göre ortalama yaş (Sadece Aktif İlanlar İçin) - Bu, her iş ilanı kartında gösterilecek
$avg_age_by_job_overall_query = "
    SELECT
        j.id as job_id,
        j.title as job_title,
        COUNT(a.id) as application_count,
        ROUND(AVG(a.age), 1) as avg_age,
        MIN(a.age) as min_age,
        MAX(a.age) as max_age
    FROM
        applications a
    JOIN
        jobs j ON a.job_id = j.id
    WHERE j.status = 'active' AND a.age IS NOT NULL
    GROUP BY
        j.id, j.title
    ORDER BY
        j.id
";
try {
    $stmt_avg_age_overall = $db->query($avg_age_by_job_overall_query);
    $avg_age_by_job_overall_raw = $stmt_avg_age_overall->fetchAll(PDO::FETCH_ASSOC);
    $avg_age_by_job_lookup = [];
    foreach ($avg_age_by_job_overall_raw as $item) {
        $avg_age_by_job_lookup[$item['job_id']] = $item;
    }
} catch (PDOException $e) {
    $avg_age_by_job_lookup = [];
    error_log("Genel ortalama yas (ise gore) sorgu hatasi: " . $e->getMessage());
}


// 2. İş ilanına göre detaylı yaş dağılımı (Sadece Aktif İlanlar İçin, yeni aralıklarla)
$age_distribution_by_job_detailed_query = "
    SELECT
        j.id as job_id,
        j.title as job_title,
        CASE
            WHEN a.age < 21 THEN '<21'
            WHEN a.age BETWEEN 21 AND 22 THEN '21-22'
            WHEN a.age BETWEEN 23 AND 24 THEN '23-24'
            WHEN a.age BETWEEN 25 AND 26 THEN '25-26'
            WHEN a.age BETWEEN 27 AND 28 THEN '27-28'
            WHEN a.age BETWEEN 29 AND 30 THEN '29-30'
            WHEN a.age BETWEEN 31 AND 32 THEN '31-32'
            WHEN a.age BETWEEN 33 AND 34 THEN '33-34'
            WHEN a.age BETWEEN 35 AND 36 THEN '35-36'
            WHEN a.age BETWEEN 37 AND 38 THEN '37-38'
            WHEN a.age BETWEEN 39 AND 40 THEN '39-40'
            WHEN a.age > 40 THEN '>40'
            ELSE 'Bilinmiyor'
        END AS age_group_label,
        COUNT(a.id) as count
    FROM
        applications a
    JOIN
        jobs j ON a.job_id = j.id
    WHERE j.status = 'active' AND a.age IS NOT NULL
    GROUP BY
        j.id, j.title, age_group_label
    ORDER BY
        j.id, MIN(a.age) -- Yaş gruplarını doğal sırasında sıralamak için
";

$age_distribution_by_job_detailed_data = [];
try {
    $stmt_dist_detailed = $db->query($age_distribution_by_job_detailed_query);
    $age_distribution_raw_detailed = $stmt_dist_detailed->fetchAll(PDO::FETCH_ASSOC);

    foreach ($age_distribution_raw_detailed as $row) {
        if (!isset($age_distribution_by_job_detailed_data[$row['job_id']])) {
            $age_distribution_by_job_detailed_data[$row['job_id']] = [
                'job_title' => $row['job_title'],
                'distribution' => []
            ];
        }
        $age_distribution_by_job_detailed_data[$row['job_id']]['distribution'][] = [
            'age_group' => $row['age_group_label'],
            'count' => $row['count']
        ];
    }
    foreach ($age_distribution_by_job_detailed_data as $job_id => &$job_data) {
        usort($job_data['distribution'], function ($a, $b) {
            $order = ['<21', '21-22', '23-24', '25-26', '27-28', '29-30', '31-32', '33-34', '35-36', '37-38', '39-40', '>40', 'Bilinmiyor'];
            return array_search($a['age_group'], $order) - array_search($b['age_group'], $order);
        });
    }
    unset($job_data);

} catch (PDOException $e) {
    $age_distribution_by_job_detailed_data = [];
    error_log("Detayli Yas dagilimi (ise gore) sorgu hatasi: " . $e->getMessage());
}


// 3. İş ilanına göre ortalama maaş beklentisi (Sadece Aktif İlanlar İçin)
$avg_salary_by_job_query = "
    SELECT
        j.id as job_id,
        j.title as job_title,
        COUNT(a.id) as application_count,
        ROUND(AVG(a.salary_expectation), 0) as avg_salary,
        MIN(a.salary_expectation) as min_salary,
        MAX(a.salary_expectation) as max_salary
    FROM
        applications a
    JOIN
        jobs j ON a.job_id = j.id
    WHERE j.status = 'active' AND a.salary_expectation IS NOT NULL AND a.salary_expectation > 0
    GROUP BY
        j.id, j.title
    ORDER BY
        application_count DESC
";
try {
    $stmt = $db->query($avg_salary_by_job_query);
    $avg_salary_by_job = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $avg_salary_by_job = [];
    error_log("Ortalama maas (ise gore) sorgu hatasi: " . $e->getMessage());
}

// 4. İş ilanına göre ortalama CV score (Sadece Aktif İlanlar İçin)
$avg_cv_score_by_job_query = "
    SELECT
        j.id as job_id,
        j.title as job_title,
        COUNT(a.id) as application_count,
        ROUND(AVG(a.cv_score), 1) as avg_cv_score,
        MIN(a.cv_score) as min_cv_score,
        MAX(a.cv_score) as max_cv_score
    FROM
        applications a
    JOIN
        jobs j ON a.job_id = j.id
    WHERE a.cv_score > 0 AND j.status = 'active'
    GROUP BY
        j.id, j.title
    ORDER BY
        avg_cv_score DESC
";
try {
    $stmt = $db->query($avg_cv_score_by_job_query);
    $avg_cv_score_by_job = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $avg_cv_score_by_job = [];
    error_log("Ortalama CV puani (ise gore) sorgu hatasi: " . $e->getMessage());
}

// YENİ: 4.1. İş ilanına göre ortalama deneyim (Sadece Aktif İlanlar İçin)
$avg_experience_by_job_query = "
    SELECT
        j.id as job_id,
        j.title as job_title,
        COUNT(a.id) as application_count,
        ROUND(AVG(a.experience), 1) as avg_experience,
        MIN(a.experience) as min_experience,
        MAX(a.experience) as max_experience
    FROM
        applications a
    JOIN
        jobs j ON a.job_id = j.id
    WHERE j.status = 'active' AND a.experience IS NOT NULL
    GROUP BY
        j.id, j.title
    ORDER BY
        avg_experience DESC
";
try {
    $stmt_exp = $db->query($avg_experience_by_job_query);
    $avg_experience_by_job = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $avg_experience_by_job = [];
    error_log("Ortalama deneyim (ise gore) sorgu hatasi: " . $e->getMessage());
}

// YENİ: 4.2. İş ilanına göre deneyim yılı - maaş beklentisi ilişkisi (Sadece Aktif İlanlar İçin)
$experience_salary_correlation_query = "
    SELECT
        j.id as job_id,
        j.title as job_title,
        a.experience,
        ROUND(AVG(a.salary_expectation), 0) as avg_salary_for_experience,
        COUNT(a.id) as count_for_experience
    FROM
        applications a
    JOIN
        jobs j ON a.job_id = j.id
    WHERE
        j.status = 'active'
        AND a.experience IS NOT NULL
        AND a.salary_expectation IS NOT NULL AND a.salary_expectation > 0
    GROUP BY
        j.id, j.title, a.experience
    ORDER BY
        j.id, a.experience ASC
";
$experience_salary_by_job_data = [];
try {
    $stmt_exp_salary = $db->query($experience_salary_correlation_query);
    $experience_salary_raw = $stmt_exp_salary->fetchAll(PDO::FETCH_ASSOC);
    foreach ($experience_salary_raw as $row) {
        if (!isset($experience_salary_by_job_data[$row['job_id']])) {
            $experience_salary_by_job_data[$row['job_id']] = [
                'job_title' => $row['job_title'],
                'points' => []
            ];
        }
        $experience_salary_by_job_data[$row['job_id']]['points'][] = [
            'experience' => (int)$row['experience'],
            'avg_salary' => (float)$row['avg_salary_for_experience'],
            'count' => (int)$row['count_for_experience']
        ];
    }
} catch (PDOException $e) {
    $experience_salary_by_job_data = [];
    error_log("Deneyim-maas iliskisi (ise gore) sorgu hatasi: " . $e->getMessage());
}


// 5. İş ilanına göre başvuru yapılan iller (en çok başvurulan 5 il, Sadece Aktif İlanlar İçin)
$city_by_job_query = "
    SELECT
        j.id as job_id,
        j.title as job_title,
        a.city,
        COUNT(a.id) as application_count,
        ROUND((COUNT(a.id) * 100.0 /
            NULLIF((SELECT COUNT(*) FROM applications app_inner WHERE app_inner.job_id = j.id AND app_inner.city IS NOT NULL AND app_inner.city <> ''), 0)
        ), 1) as percentage
    FROM
        applications a
    JOIN
        jobs j ON a.job_id = j.id
    WHERE j.status = 'active' AND a.city IS NOT NULL AND a.city <> ''
    GROUP BY
        j.id, j.title, a.city
    ORDER BY
        j.id, application_count DESC
";
try {
    $stmt = $db->query($city_by_job_query);
    $cities_by_job_temp = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cities_by_job = [];
    foreach ($cities_by_job_temp as $row) {
        if (!isset($cities_by_job[$row['job_id']])) {
            $cities_by_job[$row['job_id']] = [
                'job_title' => $row['job_title'],
                'cities' => []
            ];
        }
        if (count($cities_by_job[$row['job_id']]['cities']) < 5) {
            $cities_by_job[$row['job_id']]['cities'][] = [
                'city' => $row['city'],
                'count' => $row['application_count'],
                'percentage' => $row['percentage']
            ];
        }
    }
} catch (PDOException $e) {
    $cities_by_job = [];
    error_log("Illere gore basvuru (ise gore) sorgu hatasi: " . $e->getMessage());
}

// 6. İş ilanına göre demografik bilgiler (cinsiyet dağılımı, Sadece Aktif İlanlar İçin)
$gender_by_job_query = "
    SELECT
        j.id as job_id,
        j.title as job_title,
        a.gender,
        COUNT(a.id) as count,
        ROUND((COUNT(a.id) * 100.0 /
            NULLIF((SELECT COUNT(*) FROM applications app_inner WHERE app_inner.job_id = j.id AND app_inner.gender IS NOT NULL), 0)
        ), 1) as percentage
    FROM
        applications a
    JOIN
        jobs j ON a.job_id = j.id
    WHERE j.status = 'active' AND a.gender IS NOT NULL
    GROUP BY
        j.id, j.title, a.gender
    ORDER BY
        j.id, a.gender
";
try {
    $stmt = $db->query($gender_by_job_query);
    $gender_by_job_temp = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $gender_by_job = [];
    foreach ($gender_by_job_temp as $row) {
        if (!isset($gender_by_job[$row['job_id']])) {
            $gender_by_job[$row['job_id']] = [
                'job_title' => $row['job_title'],
                'genders' => []
            ];
        }
        $gender_key = $row['gender'] ?: 'Bilinmiyor';
         // Veritabanından gelen enum değerlerini düzeltme
        if (strtolower($gender_key) == 'male') $gender_key = 'Erkek';
        elseif (strtolower($gender_key) == 'female') $gender_key = 'Kadın';
        elseif (strtolower($gender_key) == 'other') $gender_key = 'Diğer';

        $gender_by_job[$row['job_id']]['genders'][$gender_key] = [
            'count' => $row['count'],
            'percentage' => $row['percentage']
        ];
    }
} catch (PDOException $e) {
    $gender_by_job = [];
    error_log("Cinsiyet dagilimi (ise gore) sorgu hatasi: " . $e->getMessage());
}

// 7. Yaş gruplarına göre dağılım (Genel - Tüm başvurular)
$age_distribution_query = "
    SELECT
        CASE
            WHEN age < 21 THEN '<21'
            WHEN age BETWEEN 21 AND 22 THEN '21-22'
            WHEN age BETWEEN 23 AND 24 THEN '23-24'
            WHEN age BETWEEN 25 AND 26 THEN '25-26'
            WHEN age BETWEEN 27 AND 28 THEN '27-28'
            WHEN age BETWEEN 29 AND 30 THEN '29-30'
            WHEN age BETWEEN 31 AND 32 THEN '31-32'
            WHEN age BETWEEN 33 AND 34 THEN '33-34'
            WHEN age BETWEEN 35 AND 36 THEN '35-36'
            WHEN age BETWEEN 37 AND 38 THEN '37-38'
            WHEN age BETWEEN 39 AND 40 THEN '39-40'
            WHEN age > 40 THEN '>40'
            ELSE 'Bilinmiyor'
        END AS age_group,
        COUNT(*) as count,
        ROUND((COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM applications WHERE age IS NOT NULL),0)), 1) as percentage
    FROM
        applications
    WHERE age IS NOT NULL
    GROUP BY
        age_group
    ORDER BY
        MIN(age)
";
try {
    $stmt = $db->query($age_distribution_query);
    $age_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $age_distribution = [];
    error_log("Genel yas dagilimi sorgu hatasi: " . $e->getMessage());
}

// 8. Mezun olunan bölümlere göre dağılım (Genel - Tüm başvurular)
$education_distribution_query = "
    SELECT
        education,
        COUNT(*) as count,
        ROUND((COUNT(*) * 100.0 / NULLIF((SELECT COUNT(*) FROM applications WHERE education IS NOT NULL AND education <> ''),0)), 1) as percentage
    FROM
        applications
    WHERE education IS NOT NULL AND education <> ''
    GROUP BY
        education
    ORDER BY
        count DESC
    LIMIT 10
";
try {
    $stmt = $db->query($education_distribution_query);
    $education_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $education_distribution = [];
    error_log("Genel egitim dagilimi sorgu hatasi: " . $e->getMessage());
}

// 9. İş ilanına göre en çok başvuru yapılan bölümler (Sadece Aktif İlanlar İçin)
$education_by_job_query = "
    SELECT
        j.id as job_id,
        j.title as job_title,
        a.education,
        COUNT(a.id) as application_count,
        ROUND((COUNT(a.id) * 100.0 /
            NULLIF((SELECT COUNT(*) FROM applications app_inner WHERE app_inner.job_id = j.id AND app_inner.education IS NOT NULL AND app_inner.education <> ''), 0)
        ), 1) as percentage
    FROM
        applications a
    JOIN
        jobs j ON a.job_id = j.id
    WHERE j.status = 'active' AND a.education IS NOT NULL AND a.education <> ''
    GROUP BY
        j.id, j.title, a.education
    ORDER BY
        j.id, application_count DESC
";
try {
    $stmt = $db->query($education_by_job_query);
    $education_by_job_temp = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $education_by_job = [];
    foreach ($education_by_job_temp as $row) {
        if (!isset($education_by_job[$row['job_id']])) {
            $education_by_job[$row['job_id']] = [
                'job_title' => $row['job_title'],
                'educations' => []
            ];
        }
        if (count($education_by_job[$row['job_id']]['educations']) < 5) {
            $education_by_job[$row['job_id']]['educations'][] = [
                'education' => $row['education'],
                'count' => $row['application_count'],
                'percentage' => $row['percentage']
            ];
        }
    }
} catch (PDOException $e) {
    $education_by_job = [];
    error_log("Egitim dagilimi (ise gore) sorgu hatasi: " . $e->getMessage());
}


// YENİ: 10. Haftanın Günlerine Göre Başvuru Sayıları (Genel)
$applications_by_day_of_week_query = "
    SELECT
        CASE WEEKDAY(a.created_at) 
            WHEN 0 THEN 'Pazartesi'
            WHEN 1 THEN 'Salı'
            WHEN 2 THEN 'Çarşamba'
            WHEN 3 THEN 'Perşembe'
            WHEN 4 THEN 'Cuma'
            WHEN 5 THEN 'Cumartesi'
            WHEN 6 THEN 'Pazar'
        END as day_name,
        COUNT(a.id) as application_count,
        WEEKDAY(a.created_at) as day_order 
    FROM
        applications a
    WHERE a.created_at IS NOT NULL
    GROUP BY
        day_name, day_order
    ORDER BY
        day_order ASC
";
try {
    $stmt_days = $db->query($applications_by_day_of_week_query);
    $applications_by_day_of_week = $stmt_days->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $applications_by_day_of_week = [];
    error_log("Haftalik basvuru sayilari sorgu hatasi: " . $e->getMessage());
}

// YENİ: 11. Aylık Başvuru Sayıları (Genel)
$applications_by_month_query = "
    SELECT
        DATE_FORMAT(a.created_at, '%Y-%m') as year_month,
        CONCAT(
            CASE MONTH(a.created_at)
                WHEN 1 THEN 'Ocak' WHEN 2 THEN 'Şubat' WHEN 3 THEN 'Mart'
                WHEN 4 THEN 'Nisan' WHEN 5 THEN 'Mayıs' WHEN 6 THEN 'Haziran'
                WHEN 7 THEN 'Temmuz' WHEN 8 THEN 'Ağustos' WHEN 9 THEN 'Eylül'
                WHEN 10 THEN 'Ekim' WHEN 11 THEN 'Kasım' WHEN 12 THEN 'Aralık'
            END, ' ', YEAR(a.created_at)
        ) as month_label,
        COUNT(a.id) as application_count
    FROM
        applications a
    WHERE a.created_at IS NOT NULL
    GROUP BY
        year_month, month_label
    ORDER BY
        year_month ASC
    LIMIT 12 -- Son 12 ay gibi bir limit eklenebilir
";
try {
    $stmt_months = $db->query($applications_by_month_query);
    $applications_by_month = $stmt_months->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $applications_by_month = [];
    error_log("Aylik basvuru sayilari sorgu hatasi: " . $e->getMessage());
}


// Genel İstatistikler (Summary Cards için)
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM applications");
    $total_applications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'active'");
    $active_jobs = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->query("SELECT ROUND(AVG(cv_score), 1) as avg_score FROM applications WHERE cv_score > 0");
    $avg_overall_cv_score = $stmt->fetch(PDO::FETCH_ASSOC)['avg_score'] ?? 0;

    $stmt = $db->query("SELECT ROUND(AVG(age), 1) as avg_age FROM applications WHERE age IS NOT NULL");
    $avg_overall_age = $stmt->fetch(PDO::FETCH_ASSOC)['avg_age'] ?? 0;
    
    // YENİ: Genel Ortalama Deneyim
    $stmt = $db->query("SELECT ROUND(AVG(experience), 1) as avg_exp FROM applications WHERE experience IS NOT NULL");
    $avg_overall_experience = $stmt->fetch(PDO::FETCH_ASSOC)['avg_exp'] ?? 0;

} catch (PDOException $e) {
    $total_applications = 0;
    $active_jobs = 0;
    $avg_overall_cv_score = 0;
    $avg_overall_age = 0;
    $avg_overall_experience = 0;
    error_log("Genel istatistik sorgu hatasi: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detaylı Başvuru Analitikleri | Jobbeta2</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

<style>
    :root {
        --primary: #4361ee; --primary-hover: #3a56d4; --primary-light: rgba(67, 97, 238, 0.1);
        --secondary: #747f8d; --success: #2ecc71; --success-light: rgba(46, 204, 113, 0.1);
        --danger: #e74c3c; --danger-light: rgba(231, 76, 60, 0.1);
        --warning: #f39c12; --warning-light: rgba(243, 156, 18, 0.1);
        --info: #3498db; --info-light: rgba(52, 152, 219, 0.1);
        --light: #f5f7fb; --dark: #343a40; --body-bg: #f9fafb;
        --body-color: #333; --card-bg: #ffffff; --card-border: #eaedf1; 
        --card-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        --transition-normal: all 0.2s ease-in-out;
    }
    body {
        background-color: var(--body-bg);
        color: var(--body-color);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        font-size: 0.9rem;
    }
    .admin-navbar { background-color: white; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 12px 0; }
    .admin-navbar .navbar-brand { font-weight: 600; color: var(--primary) !important; display: flex; align-items: center; }
    .admin-navbar .brand-icon { color: var(--primary); margin-right: 8px; }
    .admin-navbar .nav-link { color: var(--text-primary); padding: 0.5rem 0.8rem; border-radius: 6px; margin-right: 5px; transition: all 0.2s; display: flex; align-items: center; font-weight: 500; }
    .admin-navbar .nav-link i { margin-right: 6px; font-size: 1.1em; }
    .admin-navbar .nav-link:hover { color: var(--primary); background-color: var(--primary-light); }
    .admin-navbar .nav-link.active { color: var(--primary); background-color: var(--primary-light); font-weight: 600; }
    .admin-navbar .logout-link { color: var(--text-secondary); }
    .admin-navbar .logout-link:hover { color: var(--danger); background-color: var(--danger-light); }
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
        transition: all 0.3s ease;
    }
    
    .card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
        padding: 1.5rem;
    }
    /* Chart styles */
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
        margin-bottom: 1rem;
    }
    
    .chart-container-sm {
        position: relative;
        height: 200px;
        width: 100%;
        margin-bottom: 1rem;
    }
    .progress-bar-container { margin-bottom: 0.75rem; }
    .progress-bar-label { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem; }
    .progress-bar-label .label { color: var(--text-secondary); font-weight: 500; }
    .progress-bar-label .value { font-weight: 600; color: var(--text-primary); }
    .progress { height: 8px; border-radius: 50px; background-color: var(--light); }
    .progress-bar { border-radius: 50px; }
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
        transition: all 0.3s ease;
    }
    
    .stat-card .card-body {
        padding: 0;
        position: relative;
        z-index: 1;
    }
    
    .stat-card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transform: translateY(-2px);
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
    
    .stats-danger {
        border-left-color: var(--danger);
    }
    
    .stats-danger .stats-icon {
        background-color: rgba(231, 76, 60, 0.15);
        color: var(--danger);
    }
    /* Table styles */
    .table-responsive { 
        overflow-x: auto; 
        -webkit-overflow-scrolling: touch; 
    }
    
    .table { 
        width: 100%; 
        margin-bottom: 1rem; 
        color: var(--text-primary); 
        vertical-align: middle; 
        border-color: var(--border-color); 
    }
    
    .table th { 
        font-weight: 600; 
        padding: 0.75rem; 
        vertical-align: bottom; 
        background-color: var(--light); 
        border-bottom: 2px solid var(--border-color); 
    }
    
    .table td { 
        padding: 0.75rem; 
        vertical-align: middle; 
        border-top: 1px solid var(--border-color); 
    }
    
    .table-hover tbody tr:hover { 
        background-color: var(--primary-light); 
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
    
    .btn-outline-light {
        color: var(--primary);
        border-color: #e2e8f0;
        background-color: #fff;
    }
    
    .btn-outline-light:hover {
        background-color: var(--light);
        color: var(--primary);
        border-color: #d1d5db;
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
    /* Dashboard section styles */
    .dashboard-container {
        margin-bottom: 2rem;
    }
    
    .section-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--card-border);
    }
    
    .section-title i {
        margin-right: 0.5rem;
        color: var(--primary);
    }
    
    .section-title small {
        font-size: 0.8rem;
        font-weight: 400;
        margin-left: 0.5rem;
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
        transition: all 0.3s ease;
        height: 100%;
    }
    
    .job-detail-card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .job-detail-header { 
        padding: 1.25rem; 
        border-bottom: 1px solid var(--border-color); 
        background-color: var(--primary-light); 
    }
    
    .job-detail-title { 
        font-weight: 600; 
        margin-bottom: 0; 
        color: var(--primary); 
    }
    .job-detail-meta { display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.875rem; color: var(--text-secondary); }
    .job-detail-meta-item { display: flex; align-items: center; }
    .job-detail-meta-item i { margin-right: 0.25rem; }
    .job-detail-body { padding: 1.25rem; }
    .job-detail-section { margin-bottom: 1.5rem; }
    .job-detail-section:last-child { margin-bottom: 0; }
    .job-detail-section-title { font-weight: 600; margin-bottom: 0.75rem; font-size: 1rem; color: var(--text-primary); display: flex; align-items: center; }
    .job-detail-section-title i { margin-right: 0.5rem; color: var(--primary); }
    .session-info { display: flex; align-items: center; justify-content: flex-end; margin-bottom: 1rem; font-size: 0.875rem; color: var(--text-secondary); }
    .session-info i { margin-right: 0.25rem; }
    .trend-card-header {
        background-image: linear-gradient(45deg, var(--primary-light) 0%, rgba(255,255,255,0) 70%);
    }
    @media (max-width: 767.98px) { .job-detail-meta { flex-direction: column; gap: 0.5rem; } .stat-card { margin-bottom:1rem !important; } }
    @media (max-width: 991.98px) { .admin-navbar .navbar-nav { padding-top: 15px; } .admin-navbar .nav-link { padding: 10px; margin-bottom: 5px; } }
</style>
</head>
<body>
    <?php include 'navbar.php'; ?>


    <div class="container">
        <!-- Session Info -->
<br>
<br>

        <!-- Dashboard Summary -->
        <div class="row mb-4">
            <div class="col-lg col-md-4 col-sm-6 mb-3">
                <div class="stat-card stats-primary">
                    <div class="stats-icon">
                        <i class="bi bi-file-earmark-person"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-value"><?= number_format($total_applications) ?></div>
                        <div class="stats-label">Toplam Başvuru</div>
                    </div>
                </div>
            </div>
            <div class="col-lg col-md-4 col-sm-6 mb-3">
                <div class="stat-card stats-success">
                    <div class="stats-icon">
                        <i class="bi bi-briefcase"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-value"><?= $active_jobs ?></div>
                        <div class="stats-label">Aktif İş İlanı</div>
                    </div>
                </div>
            </div>
            <div class="col-lg col-md-4 col-sm-6 mb-3">
                <div class="stat-card stats-warning">
                    <div class="stats-icon">
                        <i class="bi bi-file-text"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-value"><?= $avg_overall_cv_score ?></div>
                        <div class="stats-label">Ort. CV Puanı (Genel)</div>
                    </div>
                </div>
            </div>
            <div class="col-lg col-md-6 col-sm-6 mb-3">
                <div class="stat-card stats-info">
                    <div class="stats-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-value"><?= $avg_overall_age ?></div>
                        <div class="stats-label">Ortalama Yaş (Genel)</div>
                    </div>
                </div>
            </div>
            <div class="col-lg col-md-6 col-sm-12 mb-3">
                <div class="stat-card stats-danger">
                    <div class="stats-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-value"><?= $avg_overall_experience ?> Yıl</div>
                        <div class="stats-label">Ort. Deneyim (Genel)</div>
                    </div>
                </div>
            </div>
        </div>


        <!-- İş İlanlarına Göre Yaş Analizi (Aktif İlanlar) -->
        <div class="dashboard-container">
            <h3 class="section-title"><i class="bi bi-people"></i> İş İlanlarına Göre Yaş Analizi (Aktif İlanlar)</h3>
            <div class="row">
                <?php
                if (count($age_distribution_by_job_detailed_data) > 0):
                    foreach ($age_distribution_by_job_detailed_data as $job_id => $job_data_detailed):
                        $avg_stats_for_this_job = $avg_age_by_job_lookup[$job_id] ?? null;
                ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="card-title mb-0"><i class="bi bi-briefcase"></i> <?= htmlspecialchars($job_data_detailed['job_title']) ?> - Yaş Analizi</h6>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <?php if ($avg_stats_for_this_job): ?>
                                        <p class="mb-2">
                                            <strong>Başvuru Sayısı:</strong> <?= $avg_stats_for_this_job['application_count'] ?><br>
                                            <strong>Ortalama Yaş:</strong> <?= $avg_stats_for_this_job['avg_age'] ?? 'N/A' ?><br>
                                            <strong>Min-Max Yaş:</strong> <span class="metric-badge badge-info"><?= $avg_stats_for_this_job['min_age'] ?? 'N/A' ?> - <?= $avg_stats_for_this_job['max_age'] ?? 'N/A' ?></span>
                                        </p>
                                        <hr class="mt-1 mb-3">
                                    <?php endif; ?>
                                    
                                    <h6 class="job-detail-section-title mb-2"><i class="bi bi-bar-chart-line"></i> Detaylı Yaş Dağılımı</h6>
                                    <?php if (!empty($job_data_detailed['distribution'])): ?>
                                        <div class="chart-container flex-grow-1" style="height: 250px;">
                                            <canvas id="ageDistributionJobDetailed_<?= $job_id ?>"></canvas>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center my-auto">Bu iş ilanı için detaylı yaş dağılım verisi bulunmamaktadır.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card"><div class="card-body text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Aktif iş ilanları için yaş analizi verisi bulunmamaktadır.</p></div></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>


        <!-- İş İlanlarına Göre Maaş Beklentisi (Aktif İlanlar) -->
        <div class="dashboard-container">
            <h3 class="section-title"><i class="bi bi-cash-stack"></i> İş İlanlarına Göre Ortalama Maaş Beklentisi (Aktif İlanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($avg_salary_by_job) > 0): ?>
                        <div class="row mb-4">
                            <div class="col-lg-6"><div class="chart-container"><canvas id="avgSalaryByJobChart"></canvas></div></div>
                            <div class="col-lg-6">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>İş İlanı</th><th>Ort. Maaş</th><th>Min-Max</th><th>Başvuru</th></tr></thead>
                                        <tbody>
                                            <?php foreach($avg_salary_by_job as $job): ?>
                                                <tr><td><?= htmlspecialchars($job['job_title']) ?></td><td><strong><?= number_format($job['avg_salary'], 0, ',', '.') ?> ₺</strong></td><td><small class="text-muted"><?= number_format($job['min_salary'], 0, ',', '.') ?> ₺ - <?= number_format($job['max_salary'], 0, ',', '.') ?> ₺</small></td><td><?= $job['application_count'] ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Aktif ilanlar için maaş beklentisi verisi bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- YENİ: İş İlanlarına Göre Ortalama Deneyim (Aktif İlanlar) -->
        <div class="dashboard-container">
            <h3 class="section-title"><i class="bi bi-calendar-check"></i> İş İlanlarına Göre Ortalama Deneyim (Aktif İlanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($avg_experience_by_job) > 0): ?>
                        <div class="row mb-4">
                            <div class="col-lg-6"><div class="chart-container"><canvas id="avgExperienceByJobChart"></canvas></div></div>
                            <div class="col-lg-6">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>İş İlanı</th><th>Ort. Deneyim (Yıl)</th><th>Min-Max</th><th>Başvuru</th></tr></thead>
                                        <tbody>
                                            <?php foreach($avg_experience_by_job as $job): ?>
                                                <tr><td><?= htmlspecialchars($job['job_title']) ?></td><td><strong><?= $job['avg_experience'] ?></strong></td><td><small class="text-muted"><?= $job['min_experience'] ?> - <?= $job['max_experience'] ?></small></td><td><?= $job['application_count'] ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Aktif ilanlar için ortalama deneyim verisi bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- YENİ: Deneyime Göre Ortalama Maaş Beklentisi (İş İlanı Bazında) -->
        <div class="dashboard-container">
            <h3 class="section-title"><i class="bi bi-graph-up-arrow"></i> Deneyime Göre Ortalama Maaş Beklentisi (Aktif İlanlar)</h3>
            <div class="row">
                <?php
                if (count($experience_salary_by_job_data) > 0):
                    foreach ($experience_salary_by_job_data as $job_id => $job_data):
                        if (!empty($job_data['points']) && count($job_data['points']) > 1) : // Only show chart if there are at least 2 data points for a line
                ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h6 class="card-title mb-0"><i class="bi bi-briefcase"></i> <?= htmlspecialchars($job_data['job_title']) ?></h6>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <h6 class="job-detail-section-title mb-2"><i class="bi bi-currency-dollar"></i> Deneyim Yılı vs. Ortalama Maaş Beklentisi</h6>
                                    <div class="chart-container flex-grow-1" style="height: 250px;">
                                        <canvas id="experienceSalaryChart_<?= $job_id ?>"></canvas>
                                    </div>
                                     <small class="text-muted mt-2 text-center">Grafik, en az 2 farklı deneyim yılına sahip başvuruları gösterir.</small>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Optionally, handle jobs with insufficient data for a line chart -->
                        <!-- 
                        <div class="col-lg-6 mb-4">
                             <div class="card h-100"><div class="card-body text-center d-flex flex-column justify-content-center">
                                <h6 class="card-title mb-2"><i class="bi bi-briefcase"></i> <?= htmlspecialchars($job_data['job_title']) ?></h6>
                                <i class="bi bi-exclamation-circle-fill text-warning fs-3 my-2"></i>
                                <p class="text-muted">Bu iş ilanı için deneyim-maaş grafiği oluşturmaya yetecek çeşitlilikte veri bulunmamaktadır.</p>
                             </div></div>
                        </div>
                        -->
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card"><div class="card-body text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Aktif iş ilanları için deneyim-maaş ilişkisi verisi bulunmamaktadır.</p></div></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>


        <!-- İş İlanlarına Göre CV Puanı (Aktif İlanlar) -->
        <div class="dashboard-container">
            <h3 class="section-title"><i class="bi bi-file-earmark-text"></i> İş İlanlarına Göre CV Puanı (Aktif İlanlar)</h3>
            <div class="card">
                <div class="card-body">
                    <?php if (count($avg_cv_score_by_job) > 0): ?>
                        <div class="row mb-4">
                            <div class="col-lg-6"><div class="chart-container"><canvas id="avgCvScoreByJobChart"></canvas></div></div>
                            <div class="col-lg-6">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>İş İlanı</th><th>Ort. CV Puanı</th><th>Min-Max</th><th>Başvuru</th></tr></thead>
                                        <tbody>
                                            <?php foreach($avg_cv_score_by_job as $job): ?>
                                                <tr><td><?= htmlspecialchars($job['job_title']) ?></td><td><strong><?= $job['avg_cv_score'] ?></strong></td><td><small class="text-muted"><?= $job['min_cv_score'] ?> - <?= $job['max_cv_score'] ?></small></td><td><?= $job['application_count'] ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Aktif ilanlar için CV puanı verisi bulunmamaktadır.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- İş İlanlarına Göre Eğitim Bölümleri (Aktif İlanlar) -->
        <div class="dashboard-container">
            <h3 class="section-title"><i class="bi bi-mortarboard"></i> İş İlanlarına Göre Eğitim Bölümleri (Aktif İlanlar)</h3>
            <div class="row">
                <?php if (count($education_by_job) > 0): ?>
                    <?php foreach ($education_by_job as $job_id => $job_data): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="job-detail-card">
                                <div class="job-detail-header"><h5 class="job-detail-title"><?= htmlspecialchars($job_data['job_title']) ?></h5></div>
                                <div class="job-detail-body">
                                    <div class="job-detail-section">
                                        <h6 class="job-detail-section-title"><i class="bi bi-mortarboard"></i> En Çok Başvuran Bölümler (Top 5)</h6>
                                        <?php if (!empty($job_data['educations'])): ?>
                                            <?php foreach ($job_data['educations'] as $education): ?>
                                                <div class="progress-bar-container"><div class="progress-bar-label"><span class="label"><?= htmlspecialchars($education['education']) ?></span><span class="value"><?= $education['count'] ?> (<?= $education['percentage'] ?? 0 ?>%)</span></div><div class="progress"><div class="progress-bar bg-primary" role="progressbar" style="width: <?= $education['percentage'] ?? 0 ?>%;"></div></div></div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted">Bu aktif ilan için eğitim verisi bulunmamaktadır.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><div class="card"><div class="card-body text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Aktif ilanlar için eğitim verisi bulunmamaktadır.</p></div></div></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- İş İlanlarına Göre Başvuru Yapılan İller (Aktif İlanlar) -->
        <div class="dashboard-container">
            <h3 class="section-title"><i class="bi bi-geo-alt"></i> İş İlanlarına Göre Başvuru Yapılan İller (Aktif İlanlar)</h3>
            <div class="row">
                <?php if (count($cities_by_job) > 0): ?>
                    <?php foreach ($cities_by_job as $job_id => $job_data): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="job-detail-card">
                                <div class="job-detail-header"><h5 class="job-detail-title"><?= htmlspecialchars($job_data['job_title']) ?></h5></div>
                                <div class="job-detail-body">
                                    <div class="job-detail-section">
                                        <h6 class="job-detail-section-title"><i class="bi bi-geo-alt"></i> En Çok Başvuru Yapılan İller (Top 5)</h6>
                                        <?php if (!empty($job_data['cities'])): ?>
                                            <?php foreach ($job_data['cities'] as $city): ?>
                                                <div class="progress-bar-container"><div class="progress-bar-label"><span class="label"><?= htmlspecialchars($city['city']) ?></span><span class="value"><?= $city['count'] ?> (<?= $city['percentage'] ?? 0 ?>%)</span></div><div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: <?= $city['percentage'] ?? 0 ?>%;"></div></div></div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted">Bu aktif ilan için şehir verisi bulunmamaktadır.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12"><div class="card"><div class="card-body text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Aktif ilanlar için şehir verisi bulunmamaktadır.</p></div></div></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- İş İlanlarına Göre Cinsiyet Dağılımı (Aktif İlanlar) -->
        <div class="dashboard-container">
            <h3 class="section-title"><i class="bi bi-gender-ambiguous"></i> İş İlanlarına Göre Cinsiyet Dağılımı (Aktif İlanlar)</h3>
            <div class="row">
                <?php if (count($gender_by_job) > 0): ?>
                    <?php foreach ($gender_by_job as $job_id => $job_data): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="job-detail-card">
                                <div class="job-detail-header"><h5 class="job-detail-title"><?= htmlspecialchars($job_data['job_title']) ?></h5></div>
                                <div class="job-detail-body">
                                    <div class="job-detail-section">
                                        <div class="row align-items-center">
                                            <div class="col-md-6"><div class="chart-container-sm"><canvas id="genderChart_<?= $job_id ?>"></canvas></div></div>
                                            <div class="col-md-6">
                                                <?php if (!empty($job_data['genders'])): ?>
                                                    <?php foreach ($job_data['genders'] as $gender => $data): ?>
                                                        <div class="progress-bar-container">
                                                            <div class="progress-bar-label"><span class="label"><?php 
                                                            $displayGender = ''; $color = '';
                                                            switch (strtolower($gender)) { 
                                                                case 'erkek': $displayGender = '<i class="bi bi-gender-male me-1"></i> Erkek'; $color = '#3498db'; break; 
                                                                case 'kadın': $displayGender = '<i class="bi bi-gender-female me-1"></i> Kadın'; $color = '#e74c3c'; break; 
                                                                case 'diğer': $displayGender = '<i class="bi bi-gender-ambiguous me-1"></i> Diğer'; $color = '#95a5a6'; break;
                                                                default: $displayGender = '<i class="bi bi-question-circle me-1"></i> '.htmlspecialchars($gender); $color = '#bdc3c7'; break; 
                                                            } 
                                                            echo $displayGender;
                                                            ?></span><span class="value"><?= $data['count'] ?> (<?= $data['percentage'] ?? 0 ?>%)</span></div>
                                                            <div class="progress"><div class="progress-bar" role="progressbar" style="width: <?= $data['percentage'] ?? 0 ?>%; background-color: <?= $color ?>;"></div></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-muted">Bu aktif ilan için cinsiyet verisi bulunmamaktadır.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                     <div class="col-12"><div class="card"><div class="card-body text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Aktif ilanlar için cinsiyet verisi bulunmamaktadır.</p></div></div></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Genel Başvuru Trendleri -->
        <div class="dashboard-container">
             <h3 class="section-title"><i class="bi bi-graph-up"></i> Genel Başvuru Trendleri <small class="text-muted ms-2"> (<?= $current_date_display ?> itibarıyla)</small></h3>
            <div class="row">
                <!-- Aylık Başvuru Sayıları -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header trend-card-header"><h6 class="card-title mb-0"><i class="bi bi-calendar3-week"></i> Aylık Başvuru Takvimi</h6></div>
                        <div class="card-body">
                            <?php if (count($applications_by_month) > 0): ?>
                                <div class="chart-container"><canvas id="monthlyApplicationsChart"></canvas></div>
                            <?php else: ?>
                                <div class="text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Aylık başvuru verisi bulunmamaktadır.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Haftanın Günlerine Göre Başvuru Sayıları -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header trend-card-header"><h6 class="card-title mb-0"><i class="bi bi-calendar-event"></i> Haftalık Başvuru Yoğunluğu</h6></div>
                        <div class="card-body">
                            <?php if (count($applications_by_day_of_week) > 0): ?>
                                <div class="chart-container"><canvas id="dailyApplicationsChart"></canvas></div>
                            <?php else: ?>
                                <div class="text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Haftalık başvuru verisi bulunmamaktadır.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Yaş Grupları Dağılımı (Genel) -->
        <div class="dashboard-container">
            <h3 class="section-title"><i class="bi bi-people"></i> Yaş Grupları Dağılımı (Genel)</h3>
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-lg-6"><div class="chart-container" style="height:350px"><canvas id="overallAgeDistributionChart"></canvas></div></div>
                        <div class="col-lg-6">
                            <?php if (count($age_distribution) > 0): ?>
                                <?php foreach ($age_distribution as $age): ?>
                                    <div class="progress-bar-container"><div class="progress-bar-label"><span class="label"><?= $age['age_group'] ?></span><span class="value"><?= $age['count'] ?> (<?= $age['percentage'] ?? 0 ?>%)</span></div><div class="progress"><div class="progress-bar bg-success" role="progressbar" style="width: <?= $age['percentage'] ?? 0 ?>%;"></div></div></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Genel yaş dağılımı için yeterli veri bulunmamaktadır.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mezun Olunan Bölümler (Genel) -->
        <div class="dashboard-container">
            <h3 class="section-title"><i class="bi bi-mortarboard"></i> Mezun Olunan Bölümler (Genel)</h3>
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-lg-6"><div class="chart-container" style="height:350px"><canvas id="overallEducationDistributionChart"></canvas></div></div>
                        <div class="col-lg-6">
                            <?php if (count($education_distribution) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>Bölüm</th><th>Sayı</th><th>Oran</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($education_distribution as $edu): ?>
                                                <tr><td><?= htmlspecialchars($edu['education']) ?></td><td><?= $edu['count'] ?></td><td><div class="progress" style="width: 100px; height: 6px; display:inline-block; margin-right: 5px;"><div class="progress-bar bg-primary" role="progressbar" style="width: <?= $edu['percentage'] ?? 0 ?>%;"></div></div> <small><?= $edu['percentage'] ?? 0 ?>%</small></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4"><i class="bi bi-info-circle text-info fs-1"></i><p class="mt-3">Genel mezun olunan bölümler için yeterli veri bulunmamaktadır.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Info Footer -->
        <div class="text-center text-muted small mb-4"><p><i class="bi bi-info-circle me-1"></i> Oturum: <?= $current_date_time ?> | Kullanıcı: <?= htmlspecialchars($current_user) ?></p></div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartColors = [
        'rgba(67, 97, 238, 0.7)', 'rgba(16, 185, 129, 0.7)', 'rgba(245, 158, 11, 0.7)',
        'rgba(59, 130, 246, 0.7)', 'rgba(239, 68, 68, 0.7)', 'rgba(107, 114, 128, 0.7)',
        'rgba(124, 58, 237, 0.7)', 'rgba(236, 72, 153, 0.7)', 'rgba(13, 202, 240, 0.7)',
        'rgba(25, 135, 84, 0.7)', 'rgba(220, 53, 69, 0.7)', 'rgba(108, 117, 125, 0.7)'
    ];
    const chartColorsSolid = [ // For pie/doughnut charts if needed with solid colors
        '#4361ee', '#10b981', '#f59e0b', '#3b82f6', '#ef4444', '#6b7280',
        '#7c3aed', '#ec4899', '#0dcaf0', '#198754', '#dc3545', '#6c757d'
    ];
     const chartColorsLine = [ // Specific colors for line charts if needed
        '#4361ee', '#10b981', '#f59e0b', '#3b82f6', '#ef4444', '#6b7280'
    ];


    const defaultChartOptions = { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { 
            legend: { display: false } 
        }, 
        scales: { 
            x: { 
                ticks: { 
                    maxRotation: 45, 
                    minRotation: 45, 
                    autoSkip: true, 
                    maxTicksLimit: 10,
                    font: { size: 10 }
                },
                grid: { display: false }
            },
            y: { 
                 beginAtZero: true,
                 ticks: { font: { size: 10 }, precision: 0 },
                 grid: { color: '#e9ecef' }
            }
        } 
    };

    function createBarChart(ctx, labels, data, label, backgroundColor, yBeginAtZero = true, yMin = undefined, yMax = undefined, customTooltipCallback = null, customYTickCallback = null) {
        if (ctx) {
            let yAxesOptions = { beginAtZero: yBeginAtZero };
            if (yMin !== undefined) yAxesOptions.min = yMin;
            if (yMax !== undefined) yAxesOptions.max = yMax;
            
            let yTicks = { font: { size: 10 }, precision: 0 };
            if (customYTickCallback) {
                yTicks.callback = customYTickCallback;
            } else if (label.includes('Maaş')) {
                 yTicks.callback = function(v) { return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(v); };
            } else if (label.includes('Deneyim')) {
                 yTicks.callback = function(v) { return v + ' Yıl'; };
                 yTicks.precision = 1; // Allow for .5 year etc.
            }
            yAxesOptions.ticks = yTicks;


            if (label.includes('CV Puanı')) {
                 if (yMax === undefined && yMin === undefined) { // Only set if not overridden
                    yAxesOptions.min = 0;
                    yAxesOptions.max = 100;
                 }
            }

            new Chart(ctx, { type: 'bar', data: { labels: labels, datasets: [{ label: label, data: data, backgroundColor: backgroundColor, borderWidth: 0, borderRadius: 4 }] },
                options: { ...defaultChartOptions,
                    plugins: { ...defaultChartOptions.plugins,
                        tooltip: {
                            callbacks: customTooltipCallback ? { label: customTooltipCallback } : 
                                (label.includes('Maaş') ? { label: function(c) { return c.dataset.label + ': ' + new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits:0 }).format(c.raw); }} : 
                                (label.includes('Deneyim') ? { label: function(c) { return `${c.dataset.label}: ${c.raw} Yıl`; }} :
                                { label: function(c) { return `${c.dataset.label}: ${c.raw}`; }} ))
                        }
                    },
                    scales: { 
                        ...defaultChartOptions.scales, 
                        y: { ...defaultChartOptions.scales.y, ...yAxesOptions } 
                    }
                }
            });
        }
    }
    function createPieChart(ctx, labels, data, legendPosition = 'right', customTooltipCallback = null) {
        if (ctx) {
            new Chart(ctx, { type: 'pie', data: { labels: labels, datasets: [{ data: data, backgroundColor: chartColorsSolid, borderWidth: 1 }] }, 
            options: { responsive: true, maintainAspectRatio: false, 
                plugins: { 
                    legend: { position: legendPosition, labels: { boxWidth: 12, padding: 10, font: {size: 10} } },
                    tooltip: customTooltipCallback ? { callbacks: { label: customTooltipCallback } } : 
                               { callbacks: { label: function(c) { const total = c.dataset.data.reduce((a, b) => a + b, 0); const perc = Math.round((c.raw / total) * 100); return `${c.label}: ${c.raw} (${perc}%)`; } } }
                } 
            } });
        }
    }
     function createDoughnutChart(ctx, labels, data, localColors) {
        if (ctx) {
            new Chart(ctx, { type: 'doughnut', data: { labels: labels, datasets: [{ data: data, backgroundColor: localColors, borderWidth: 1 }] }, 
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '70%' } });
        }
    }

    function createLineChart(ctx, labels, dataPoints, datasetLabel, borderColor) {
        if (ctx) {
            const data = dataPoints.map(p => p.avg_salary);
            const counts = dataPoints.map(p => p.count);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels, // Experience years
                    datasets: [{
                        label: datasetLabel, // Avg Salary
                        data: data,         // Salary values
                        borderColor: borderColor,
                        backgroundColor: borderColor.replace('0.7', '0.1'), // Lighter fill
                        fill: true,
                        tension: 0.1,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: borderColor,
                        meta: { counts: counts } // Store counts here
                    }]
                },
                options: {
                    ...defaultChartOptions,
                    scales: {
                        x: {
                            ...defaultChartOptions.scales.x,
                            title: { display: true, text: 'Deneyim (Yıl)', font: {size: 10} }
                        },
                        y: {
                            ...defaultChartOptions.scales.y,
                            ticks: {
                                ...defaultChartOptions.scales.y.ticks,
                                callback: function(value) {
                                    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(value);
                                }
                            },
                            title: { display: true, text: 'Ort. Maaş Beklentisi', font: {size: 10} }
                        }
                    },
                    plugins: {
                        legend: { display: true, position: 'top', labels: {font: {size: 10}} },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits:0 }).format(context.parsed.y);
                                    }
                                    return label;
                                },
                                afterLabel: function(context) {
                                    const count = context.dataset.meta.counts[context.dataIndex];
                                    return `Başvuru Sayısı: ${count}`;
                                }
                            }
                        }
                    }
                }
            });
        }
    }


    // İş İlanı Bazında Detaylı Yaş Dağılımları (Aktif İlanlar)
    <?php if (count($age_distribution_by_job_detailed_data) > 0): ?>
        <?php foreach ($age_distribution_by_job_detailed_data as $job_id => $job_data_detailed): ?>
            <?php if (!empty($job_data_detailed['distribution'])): ?>
                createBarChart(document.getElementById('ageDistributionJobDetailed_<?= $job_id ?>')?.getContext('2d'),
                    <?= json_encode(array_column($job_data_detailed['distribution'], 'age_group')) ?>,
                    <?= json_encode(array_column($job_data_detailed['distribution'], 'count')) ?>,
                    'Başvuru Sayısı', 
                    chartColors[0 % chartColors.length],
                    true 
                );
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    // Ortalama Maaş (Aktif İşe Göre)
    <?php if (count($avg_salary_by_job) > 0): ?>
    createBarChart(document.getElementById('avgSalaryByJobChart')?.getContext('2d'),
        <?= json_encode(array_map(function($job){ return strlen($job['job_title']) > 20 ? substr($job['job_title'],0,18).'...' : $job['job_title']; }, $avg_salary_by_job)) ?>,
        <?= json_encode(array_column($avg_salary_by_job, 'avg_salary')) ?>,
        'Ortalama Maaş', chartColors[2], false 
    );
    <?php endif; ?>
    
    // YENİ: Ortalama Deneyim (Aktif İşe Göre)
    <?php if (count($avg_experience_by_job) > 0): ?>
    createBarChart(document.getElementById('avgExperienceByJobChart')?.getContext('2d'),
        <?= json_encode(array_map(function($job){ return strlen($job['job_title']) > 20 ? substr($job['job_title'],0,18).'...' : $job['job_title']; }, $avg_experience_by_job)) ?>,
        <?= json_encode(array_column($avg_experience_by_job, 'avg_experience')) ?>,
        'Ortalama Deneyim', chartColors[5], true 
    );
    <?php endif; ?>

    // YENİ: Deneyime Göre Ortalama Maaş Beklentisi (İş İlanı Bazında)
    <?php
    if (count($experience_salary_by_job_data) > 0):
        $lineChartColorIndex = 0;
        foreach ($experience_salary_by_job_data as $job_id => $job_data):
            if (!empty($job_data['points']) && count($job_data['points']) > 1):
                $labels = json_encode(array_column($job_data['points'], 'experience'));
                // $salaries = json_encode(array_column($job_data['points'], 'avg_salary'));
                // $counts = json_encode(array_column($job_data['points'], 'count'));
                $points_json = json_encode($job_data['points']); // Pass all points data
    ?>
            createLineChart(
                document.getElementById('experienceSalaryChart_<?= $job_id ?>')?.getContext('2d'),
                <?= $labels ?>,
                <?= $points_json ?>,
                'Ort. Maaş Beklentisi',
                chartColorsLine[<?= $lineChartColorIndex ?> % chartColorsLine.length]
            );
    <?php
                $lineChartColorIndex++;
            endif;
        endforeach;
    endif;
    ?>


    // CV Puanı Ortalaması (Aktif İşe Göre)
    <?php if (count($avg_cv_score_by_job) > 0): ?>
    createBarChart(document.getElementById('avgCvScoreByJobChart')?.getContext('2d'),
        <?= json_encode(array_map(function($job){ return strlen($job['job_title']) > 20 ? substr($job['job_title'],0,18).'...' : $job['job_title']; }, $avg_cv_score_by_job)) ?>,
        <?= json_encode(array_column($avg_cv_score_by_job, 'avg_cv_score')) ?>,
        'Ortalama CV Puanı', chartColors[4], true, 0, 100
    );
    <?php endif; ?>

    // Cinsiyet Dağılımı (Aktif İşe Göre)
    <?php if (count($gender_by_job) > 0): ?>
        <?php foreach ($gender_by_job as $job_id => $job_data): ?>
            <?php if (!empty($job_data['genders'])): ?>
                const genderLabels_<?= $job_id ?> = []; 
                const genderData_<?= $job_id ?> = []; 
                const genderColorsLocal_<?= $job_id ?> = [];
                <?php foreach ($job_data['genders'] as $gender => $data): ?>
                    genderLabels_<?= $job_id ?>.push('<?= htmlspecialchars($gender) ?>');
                    genderData_<?= $job_id ?>.push(<?= $data['count'] ?>);
                    <?php
                        $gColor = "rgba(100,100,100,0.8)"; // default
                        if (strtolower($gender) == 'erkek') $gColor = "rgba(52, 152, 219, 0.8)";
                        elseif (strtolower($gender) == 'kadın') $gColor = "rgba(231, 76, 60, 0.8)";
                        elseif (strtolower($gender) == 'diğer') $gColor = "rgba(149, 165, 166, 0.8)";
                    ?>
                    genderColorsLocal_<?= $job_id ?>.push('<?= $gColor ?>');
                <?php endforeach; ?>
                if(document.getElementById('genderChart_<?= $job_id ?>')) {
                   createDoughnutChart(document.getElementById('genderChart_<?= $job_id ?>').getContext('2d'), genderLabels_<?= $job_id ?>, genderData_<?= $job_id ?>, genderColorsLocal_<?= $job_id ?>);
                }
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    // YENİ: Aylık Başvuru Sayıları (Genel)
    <?php if (count($applications_by_month) > 0): ?>
    createBarChart(document.getElementById('monthlyApplicationsChart')?.getContext('2d'),
        <?= json_encode(array_column($applications_by_month, 'month_label')) ?>,
        <?= json_encode(array_column($applications_by_month, 'application_count')) ?>,
        'Başvuru Sayısı', chartColors[6], true
    );
    <?php endif; ?>

    // YENİ: Haftanın Günlerine Göre Başvuru Sayıları (Genel)
    <?php if (count($applications_by_day_of_week) > 0): ?>
    createBarChart(document.getElementById('dailyApplicationsChart')?.getContext('2d'),
        <?= json_encode(array_column($applications_by_day_of_week, 'day_name')) ?>,
        <?= json_encode(array_column($applications_by_day_of_week, 'application_count')) ?>,
        'Başvuru Sayısı', chartColors[7], true
    );
    <?php endif; ?>


    // Genel Yaş Dağılımı
    <?php if (count($age_distribution) > 0): ?>
    createPieChart(document.getElementById('overallAgeDistributionChart')?.getContext('2d'),
        <?= json_encode(array_column($age_distribution, 'age_group')) ?>,
        <?= json_encode(array_column($age_distribution, 'count')) ?>,
        'right'
    );
    <?php endif; ?>

    // Genel Eğitim Dağılımı
    <?php if (count($education_distribution) > 0): ?>
    createPieChart(document.getElementById('overallEducationDistributionChart')?.getContext('2d'),
        <?= json_encode(array_column($education_distribution, 'education')) ?>,
        <?= json_encode(array_column($education_distribution, 'count')) ?>,
        'bottom' // Legend at bottom for this one
    );
    <?php endif; ?>

});
</script>
</body>
</html>
<?php
ob_end_flush();
?>