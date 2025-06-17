<?php
// Output buffering başlat
ob_start();

require_once 'config/db.php';
require_once 'includes/header.php';

// Parametreleri kontrol et
$application_id = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

if ($application_id <= 0 || $job_id <= 0) {
header('Location: index.php');
exit;
}

// Başvuru bilgilerini kontrol et
$stmt = $db->prepare("SELECT * FROM applications WHERE id = ? AND job_id = ?");
$stmt->execute([$application_id, $job_id]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
header('Location: index.php');
exit;
}

// İş ilanını kontrol et
$stmt = $db->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
header('Location: index.php');
exit;
}

// Session'da soru seti ve timer kontrolü
session_start();
$session_key = "quiz_questions_{$application_id}_{$job_id}";
$timer_key = "quiz_timer_{$application_id}_{$job_id}";

if (isset($_SESSION[$session_key]) && !empty($_SESSION[$session_key])) {
// Session'daki soruları kullan (aynı quiz'e tekrar girilirse aynı sorular gösterilsin)
$questions = $_SESSION[$session_key];

// Timer bilgisini kontrol et
if (!isset($_SESSION[$timer_key])) {
$_SESSION[$timer_key] = time(); // İlk giriş zamanını kaydet
}
$quiz_start_time = $_SESSION[$timer_key];
} else {
// Yeni soru seti oluştur
$stmt = $db->prepare("
SELECT jtc.template_id, jtc.question_count, qt.template_name
FROM job_template_configs jtc
JOIN question_templates qt ON jtc.template_id = qt.id
WHERE jtc.job_id = ?
");
$stmt->execute([$job_id]);
$template_configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$questions = [];

foreach ($template_configs as $config) {
$template_id = (int)$config['template_id'];
$question_count = (int)$config['question_count'];

$sql = "
SELECT tq.*
FROM template_questions tq
WHERE tq.template_id = ?
ORDER BY RAND()
LIMIT $question_count
";

$stmt = $db->prepare($sql);
$stmt->execute([$template_id]);
$template_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$questions = array_merge($questions, $template_questions);
}

shuffle($questions);

// Session'a kaydet
$_SESSION[$session_key] = $questions;
$_SESSION[$timer_key] = time(); // Timer'ı başlat
$quiz_start_time = $_SESSION[$timer_key];
}

// Geçen süreyi hesapla
$elapsed_seconds = time() - $quiz_start_time;
$remaining_seconds = max(0, (20 * 60) - $elapsed_seconds); // 20 dakika - geçen süre

// Süre dolmuşsa otomatik timeout
if ($remaining_seconds <= 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
$timeout_expired = true;
} else {
$timeout_expired = false;
}

// Çoktan seçmeli soruların şıklarını çek
$options = [];
$correct_options = [];

foreach ($questions as $question) {
if ($question['question_type'] == 'multiple_choice') {
$stmt = $db->prepare("
SELECT * FROM template_options
WHERE template_question_id = ?
ORDER BY RAND()
");
$stmt->execute([$question['id']]);
$question_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
$options[$question['id']] = $question_options;

foreach ($question_options as $option) {
if ($option['is_correct'] == 1) {
$correct_options[$question['id']] = $option['id'];
break;
}
}
}
}

$success = false;
$error = '';

// Form gönderildiyse yanıtları kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
try {
$db->beginTransaction();

$total_score = 0;

$multiple_choice_questions = array_filter($questions, function($q) {
return $q['question_type'] == 'multiple_choice';
});

$open_ended_questions = array_filter($questions, function($q) {
return $q['question_type'] !== 'multiple_choice';
});

$posted_answers = $_POST['answers'] ?? [];

// Çoktan seçmeli sorular için
foreach ($multiple_choice_questions as $question) {
$template_question_id = (int)$question['id'];

if (isset($posted_answers[$template_question_id]) &&
!empty($posted_answers[$template_question_id])) {

$option_id = (int)$posted_answers[$template_question_id];
$is_correct_score = 0;

if (isset($correct_options[$template_question_id]) &&
$option_id == $correct_options[$template_question_id]) {
$total_score++;
$is_correct_score = 100;
}

$stmt = $db->prepare("
INSERT INTO application_answers (application_id, question_id, option_id, answer_text, answer_score)
VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$application_id, $template_question_id, $option_id, $question['question_text'], $is_correct_score]);

} else {
$stmt = $db->prepare("
INSERT INTO application_answers (application_id, question_id, option_id, answer_text, answer_score)
VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$application_id, $template_question_id, 0, $question['question_text'] . ' (Yanıtlanmadı)', 0]);
}
}

// Açık uçlu sorular için
foreach ($open_ended_questions as $question) {
$template_question_id = (int)$question['id'];
$answer_text = '';
$answer_file_path = '';
$has_answer = false;

if (isset($posted_answers[$template_question_id])) {
$answer_text = trim($posted_answers[$template_question_id]);
if (!empty($answer_text)) {
$has_answer = true;
}
}

if (isset($_FILES['answer_file']) &&
isset($_FILES['answer_file']['name'][$template_question_id]) &&
!empty($_FILES['answer_file']['name'][$template_question_id]) &&
$_FILES['answer_file']['error'][$template_question_id] === UPLOAD_ERR_OK) {

$upload_dir = 'uploads/answers/';
if (!file_exists($upload_dir)) {
mkdir($upload_dir, 0777, true);
}

$file_tmp = $_FILES['answer_file']['tmp_name'][$template_question_id];
$file_name = $_FILES['answer_file']['name'][$template_question_id];
$file_size = $_FILES['answer_file']['size'][$template_question_id];

$allowed_types = ['application/pdf'];
$file_type = mime_content_type($file_tmp);

if (!in_array($file_type, $allowed_types)) {
throw new Exception("Yalnızca PDF dosyaları yükleyebilirsiniz.");
}

if ($file_size > 5 * 1024 * 1024) {
throw new Exception("Dosya boyutu 5MB'ı geçemez.");
}

$new_file_name = time() . '_' . $application_id . '_' . $template_question_id . '_' . basename($file_name);
$upload_path = $upload_dir . $new_file_name;

if (move_uploaded_file($file_tmp, $upload_path)) {
$answer_file_path = $upload_path;
$has_answer = true;
} else {
throw new Exception("Dosya yüklenirken bir hata oluştu: " . $file_name);
}
} elseif (isset($_FILES['answer_file']['error'][$template_question_id]) && $_FILES['answer_file']['error'][$template_question_id] !== UPLOAD_ERR_NO_FILE) {
throw new Exception("Dosya yükleme hatası: " . $_FILES['answer_file']['error'][$template_question_id] . " (" . $file_name . ")");
}

if (!$has_answer && $question['question_type'] !== 'multiple_choice') {
$answer_text_to_save = '(Yanıtlanmadı)';
} else {
$answer_text_to_save = $answer_text;
}

$stmt = $db->prepare("
INSERT INTO application_answers (application_id, question_id, answer_text, answer_file_path, answer_score)
VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$application_id, $template_question_id, $answer_text_to_save, $answer_file_path, 0]);
}

unset($_SESSION[$session_key]);
unset($_SESSION[$timer_key]);

$stmt = $db->prepare("UPDATE applications SET status = 'completed', score = ? WHERE id = ?");
$stmt->execute([$total_score, $application_id]);

$db->commit();
$success = true;

} catch (Exception $e) {
if ($db->inTransaction()) {
$db->rollBack();
}
$error = $e->getMessage();
error_log("Quiz Error: " . $e->getMessage());
}
}
?>

<!DOCTYPE html>

<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quiz - <?= htmlspecialchars($job['title']) ?></title>
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

    * {
        user-select: none;
    }

    textarea, input[type="text"], input[type="email"], input[type="search"] {
        user-select: text !important;
    }

    .quiz-wrapper {
        padding: 2rem 0;
        min-height: 100vh;
        position: relative;
    }

    .quiz-wrapper::before {
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

    .quiz-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2.5rem;
        padding: 1.5rem 2rem;
        background: var(--bg-glass);
        backdrop-filter: blur(10px);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow);
        flex-wrap: wrap;
        gap: 1.5rem;
        position: sticky; /* MODIFIED: Make header sticky */
        top: 0;           /* MODIFIED: Stick to the top */
        z-index: 1020;    /* MODIFIED: Ensure it's above other content */
    }

    .quiz-meta h1 {
        font-size: 1.875rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-dark);
        letter-spacing: -0.025em;
    }

    .quiz-meta p {
        font-size: 0.875rem;
        color: var(--text-light);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
        font-weight: 500;
    }

    .quiz-timer {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .timer-badge {
        display: flex;
        align-items: center;
        background: linear-gradient(135deg, var(--primary-light) 0%, rgba(79, 70, 229, 0.1) 100%);
        color: var(--primary);
        padding: 0.875rem 1.5rem;
        border-radius: 50px;
        font-weight: 700;
        gap: 0.625rem;
        box-shadow: var(--shadow);
        border: 1px solid rgba(79, 70, 229, 0.2);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(10px);
    }

    .timer-badge.warning {
        background: linear-gradient(135deg, var(--warning-light) 0%, rgba(245, 158, 11, 0.1) 100%);
        color: var(--warning);
        border-color: rgba(245, 158, 11, 0.3);
    }

    .timer-badge.danger {
        background: linear-gradient(135deg, var(--danger-light) 0%, rgba(239, 68, 68, 0.1) 100%);
        color: var(--danger);
        border-color: rgba(239, 68, 68, 0.3);
        animation: pulse 2s infinite;
    }

    .question-count {
        background: var(--bg-white);
        color: var(--text-light);
        padding: 0.625rem 1.125rem;
        border-radius: 50px;
        font-size: 0.875rem;
        font-weight: 600;
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-sm);
        backdrop-filter: blur(10px);
    }

    .quiz-content {
        background: var(--bg-white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        overflow: hidden;
        margin-bottom: 2.5rem;
        border: 1px solid var(--border-light);
        backdrop-filter: blur(10px);
    }

    .quiz-intro {
        padding: 2rem 2.5rem;
        border-bottom: 1px solid var(--border-light);
        background: linear-gradient(135deg, #fafafa 0%, var(--bg-white) 100%);
    }

    .quiz-intro h2 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        color: var(--text-dark);
        letter-spacing: -0.025em;
    }

    .quiz-intro p {
        color: var(--text-light);
        font-size: 1rem;
        margin-bottom: 1.5rem;
        line-height: 1.7;
    }

    .quiz-form {
        padding: 2.5rem;
    }

    .questions-container {
        display: flex;
        flex-direction: column;
        gap: 2.5rem;
        margin-bottom: 3rem;
    }

    .question-box {
        background: var(--bg-white);
        border-radius: var(--radius);
        border: 1px solid var(--border-light);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        position: relative;
    }

    .question-box::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(180deg, var(--primary) 0%, var(--info) 100%);
        transition: all 0.3s ease;
    }

    .question-box:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-4px);
        border-color: var(--primary);
    }

    .question-box:hover::before {
        width: 6px;
    }

    .question-header {
        padding: 1.75rem 2rem;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        gap: 1.25rem;
        align-items: flex-start;
        background: linear-gradient(135deg, rgba(248, 250, 252, 0.8) 0%, var(--bg-white) 100%);
    }

    .question-number {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border-radius: 50%;
        font-weight: 700;
        font-size: 0.875rem;
        flex-shrink: 0;
        box-shadow: var(--shadow);
        position: relative;
    }

    .question-number::after {
        content: '';
        position: absolute;
        inset: -2px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--info));
        z-index: -1;
        opacity: 0.2;
    }

    .question-content { /* This class name is reused, ensure no conflicts */
        flex-grow: 1;
        min-width: 0;
    }

    .question-text {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-dark);
        margin: 0;
        line-height: 1.6;
        letter-spacing: -0.025em;
    }

    .question-body {
        padding: 2rem;
    }

    .options-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .option-card {
        display: flex;
        padding: 1.25rem 1.5rem;
        background: var(--bg-white);
        border: 2px solid var(--border-light);
        border-radius: var(--radius);
        cursor: pointer;
        position: relative;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        align-items: center;
        backdrop-filter: blur(10px);
    }

    .option-card:hover {
        border-color: var(--primary);
        background: var(--primary-light);
        box-shadow: var(--shadow);
        transform: translateX(4px);
    }

    .option-card input {
        margin-right: 1rem;
        width: 1.25rem;
        height: 1.25rem;
        flex-shrink: 0;
        cursor: pointer;
        accent-color: var(--primary);
    }

    .option-card input:checked + .option-text {
        color: var(--primary);
        font-weight: 600;
    }

    .option-card.selected {
        border-color: var(--primary);
        background: var(--primary-light);
        box-shadow: var(--shadow);
        transform: translateX(8px);
    }

    .option-card.selected::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 4px;
        background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
        border-radius: 0 var(--radius) var(--radius) 0;
    }

    .option-text {
        font-size: 1rem;
        line-height: 1.6;
        transition: all 0.3s ease;
        flex-grow: 1;
        font-weight: 500;
    }

    .open-ended-question {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.75rem;
        font-weight: 600;
        color: var(--text-dark);
        font-size: 0.9375rem;
        letter-spacing: -0.025em;
    }

    .form-control {
        width: 100%;
        padding: 1rem 1.25rem;
        border: 2px solid var(--border-light);
        border-radius: var(--radius);
        background: var(--bg-white);
        color: var(--text-dark);
        font-size: 1rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: var(--shadow-sm);
        font-family: inherit;
    }

    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1), var(--shadow);
        background: rgba(79, 70, 229, 0.02);
    }

    textarea.form-control {
        min-height: 8rem;
        resize: vertical;
        line-height: 1.7;
    }

    .divider {
        display: flex;
        align-items: center;
        text-align: center;
        color: var(--text-light);
        margin: 1.5rem 0;
    }

    .divider:before,
    .divider:after {
        content: '';
        flex: 1;
        border-bottom: 2px solid var(--border-light);
    }

    .divider span {
        padding: 0 1.5rem;
        font-size: 0.875rem;
        color: var(--text-light);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }

    .file-upload {
        position: relative;
    }

    .file-upload input[type="file"] {
        width: 0.1px;
        height: 0.1px;
        opacity: 0;
        overflow: hidden;
        position: absolute;
        z-index: -1;
    }

    .file-label {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 1.25rem 1.5rem;
        background: linear-gradient(135deg, var(--primary-light) 0%, rgba(79, 70, 229, 0.1) 100%);
        color: var(--primary);
        border-radius: var(--radius);
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px dashed rgba(79, 70, 229, 0.3);
        backdrop-filter: blur(10px);
    }

    .file-label:hover {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.15) 0%, rgba(79, 70, 229, 0.05) 100%);
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .file-info {
        margin-top: 0.75rem;
        font-size: 0.875rem;
        color: var(--text-light);
        text-align: center;
        font-weight: 500;
    }

    .submit-section {
        display: flex;
        justify-content: center;
        margin-top: 3rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        background: none;
        border: none;
        cursor: pointer;
        font-family: inherit;
        font-size: 1rem;
        font-weight: 600;
        padding: 1rem 2rem;
        border-radius: var(--radius);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--primary);
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-xl);
    }

    .btn-primary::before {
        background: linear-gradient(135deg, var(--primary-dark) 0%, #312e81 100%);
        opacity: 0;
    }

    .btn-primary:hover::before {
        opacity: 1;
    }

    .btn-submit {
        padding: 1.25rem 2.5rem;
        border-radius: 50px;
        font-size: 1.125rem;
        font-weight: 700;
        letter-spacing: -0.025em;
    }

    .alert {
        padding: 1.25rem 1.5rem;
        border-radius: var(--radius);
        margin-bottom: 2rem;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        border: 1px solid transparent;
        backdrop-filter: blur(10px);
    }

    .alert-danger {
        background: linear-gradient(135deg, var(--danger-light) 0%, rgba(239, 68, 68, 0.1) 100%);
        color: var(--danger);
        border-color: rgba(239, 68, 68, 0.3);
    }

    .alert-warning {
        background: linear-gradient(135deg, var(--warning-light) 0%, rgba(245, 158, 11, 0.1) 100%);
        color: var(--warning);
        border-color: rgba(245, 158, 11, 0.3);
    }

    .alert-info {
        background: linear-gradient(135deg, var(--info-light) 0%, rgba(59, 130, 246, 0.1) 100%);
        color: var(--info);
        border-color: rgba(59, 130, 246, 0.3);
    }

    .alert-icon {
        font-size: 1.375rem;
        flex-shrink: 0;
    }

    .alert-content { /* This class name is reused, ensure no conflicts */
        flex-grow: 1;
    }

    .alert-title {
        font-weight: 700;
        margin-bottom: 0.25rem;
        font-size: 1rem;
        letter-spacing: -0.025em;
    }

    .alert-text {
        font-size: 0.875rem;
        margin: 0;
        line-height: 1.6;
    }

    .success-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
    }

    .success-card {
        background: var(--bg-white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        padding: 4rem 3rem;
        text-align: center;
        max-width: 600px;
        width: 100%;
        border: 1px solid var(--border-light);
        backdrop-filter: blur(10px);
        position: relative;
    }

    .success-card::before {
        content: '';
        position: absolute;
        inset: -1px;
        border-radius: inherit;
        background: linear-gradient(135deg, var(--primary), var(--info), var(--success));
        z-index: -1;
        opacity: 0.1;
    }

    .success-icon {
        font-size: 5rem;
        color: var(--success);
        margin-bottom: 2rem;
        display: inline-flex;
    }

    .success-icon i {
        background: linear-gradient(135deg, var(--success-light) 0%, rgba(16, 185, 129, 0.1) 100%);
        border-radius: 50%;
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        backdrop-filter: blur(10px);
    }

    .success-card h2 {
        font-size: 2rem;
        margin-bottom: 1rem;
        color: var(--text-dark);
        font-weight: 800;
        letter-spacing: -0.025em;
    }

    .success-card p {
        color: var(--text-light);
        margin-bottom: 2.5rem;
        font-size: 1.125rem;
        line-height: 1.7;
    }

    .timeout-card {
        background: var(--bg-white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        padding: 4rem 3rem;
        text-align: center;
        max-width: 600px;
        width: 100%;
        border: 1px solid var(--border-light);
        backdrop-filter: blur(10px);
        position: relative;
    }

    .timeout-card::before {
        content: '';
        position: absolute;
        inset: -1px;
        border-radius: inherit;
        background: linear-gradient(135deg, var(--danger), var(--warning));
        z-index: -1;
        opacity: 0.1;
    }

    .timeout-icon {
        font-size: 5rem;
        color: var(--danger);
        margin-bottom: 2rem;
        display: inline-flex;
    }

    .timeout-icon i {
        background: linear-gradient(135deg, var(--danger-light) 0%, rgba(239, 68, 68, 0.1) 100%);
        border-radius: 50%;
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        backdrop-filter: blur(10px);
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

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

    .question-box {
        animation: slideInUp 0.6s ease-out;
    }

    .question-box:nth-child(n) {
        animation-delay: calc(0.1s * var(--animation-order, 0));
    }

    @media (max-width: 768px) {
        .container {
            padding: 0 1rem;
        }
        
        .quiz-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1.5rem;
            padding: 1.25rem 1.5rem;
             /* MODIFIED: Adjust border-radius for sticky header on mobile if needed, current looks ok */
            border-radius: 0 0 var(--radius) var(--radius); /* Example: round bottom corners only when sticky */
        }
        
        .quiz-timer {
            width: 100%;
            justify-content: space-between;
        }
        
        .question-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem;
        }
        
        .quiz-form {
            padding: 1.5rem;
        }
        
        .quiz-intro {
            padding: 1.5rem;
        }
        
        .success-card, .timeout-card {
            padding: 2.5rem 1.5rem;
            margin: 1rem;
        }
        
        .question-body {
            padding: 1.5rem;
        }
        
        .question-text {
            font-size: 1rem;
        }
        
        .quiz-meta h1 {
            font-size: 1.5rem;
        }
    }

    .btn:focus,
    .form-control:focus,
    .option-card:focus-within {
        outline: 2px solid var(--primary);
        outline-offset: 2px;
    }

    ::selection {
        background: rgba(79, 70, 229, 0.2);
        color: var(--text-dark);
    }

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
</style>

</head>
<body>
<div class="quiz-wrapper">
<div class="container">
<?php if ($timeout_expired): ?>
<div class="success-container">
<div class="timeout-card">
<div class="timeout-icon">
<i class="bi bi-clock-fill"></i>
</div>
<h2>Süre Doldu!</h2>
<p>Üzgünüz, quiz süresi dolmuştur. Yanıtlarınız kaydedilmemiş olabilir. Lütfen daha sonra tekrar deneyiniz veya bizimle iletişime geçiniz.</p>
<a href="index.php" class="btn btn-primary">
<i class="bi bi-house-door"></i>
Ana Sayfaya Dön
</a>
</div>
</div>
<?php elseif ($success): ?>
<div class="success-container">
<div class="success-card">
<div class="success-icon">
<i class="bi bi-check-circle-fill"></i>
</div>
<h2>Başvurunuz Tamamlandı!</h2>
<p>Değerli zamanınızı ayırıp başvurunuzu tamamladığınız için teşekkür ederiz. Başvurunuz başarıyla alınmıştır. Ekibimiz en kısa sürede değerlendirecek ve uygun bulunması halinde sizinle iletişime geçecektir.</p>
<a href="index.php" class="btn btn-primary mt-4">
<i class="bi bi-house-door"></i>
Ana Sayfaya Dön
</a>
</div>
</div>
<?php elseif (empty($questions)): ?>
<div class="success-container">
<div class="success-card">
<div class="success-icon">
<i class="bi bi-check-circle-fill"></i>
</div>
<h2>Başvurunuz Alındı!</h2>
<p>Başvurunuz için teşekkür ederiz. Bu pozisyon için ek değerlendirme soruları bulunmamaktadır. Başvurunuz işleme alınmıştır.</p>
<a href="index.php" class="btn btn-primary mt-4">
<i class="bi bi-house-door"></i>
Ana Sayfaya Dön
</a>
</div>
</div>
<?php else: ?>
<div class="quiz-header">
<div class="quiz-meta">
<h1><?= htmlspecialchars($job['title']) ?></h1>
<p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($job['location']) ?></p>
</div>
<div class="quiz-timer">
<div class="timer-badge" id="countdown-timer">
<i class="bi bi-clock"></i>
<span id="timer">20:00</span>
</div>
<div class="question-count">
<i class="bi bi-list-check me-1"></i>
<?= count($questions) ?> Soru
</div>
</div>
</div>

<?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <div class="alert-icon">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-title">Hata Oluştu</div>
                        <p class="alert-text"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="quiz-content">
                <div class="quiz-intro">
                    <h2>Değerlendirme Soruları</h2>
                    <p>Lütfen aşağıdaki soruları dikkatlice yanıtlayarak başvurunuzu tamamlayın.</p>
                    <div class="alert alert-info">
                        <div class="alert-icon">
                            <i class="bi bi-info-circle-fill"></i>
                        </div>
                        <div class="alert-content">
                            <div class="alert-title">Önemli Bilgi</div>
                            <p class="alert-text">Tüm sorular otomatik olarak kaydedilecektir. Eksik yanıtlar boş olarak işaretlenecektir. Ctrl+V ile yapıştırma işlemi yapabilirsiniz. F5 ile sayfayı yenilerseniz sorularınız değişmez ve kaldığınız süre ile devam edersiniz.</p>
                        </div>
                    </div>
                </div>
                
                <form method="post" enctype="multipart/form-data" id="quiz-form" class="quiz-form">
                    <div class="questions-container">
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="question-box" style="--animation-order: <?= $index ?>">
                                <div class="question-header">
                                    <span class="question-number"><?= ($index + 1) ?></span>
                                    <div class="question-content">
                                        <h3 class="question-text"><?= htmlspecialchars($question['question_text']) ?></h3>
                                    </div>
                                </div>
                                
                                <div class="question-body">
                                    <?php if ($question['question_type'] == 'multiple_choice'): ?>
                                        <?php if (isset($options[$question['id']])): ?>
                                            <div class="options-list">
                                                <?php foreach ($options[$question['id']] as $option): ?>
                                                    <label class="option-card" for="option_<?= $option['id'] ?>">
                                                        <input type="radio" 
                                                               name="answers[<?= $question['id'] ?>]" 
                                                               id="option_<?= $option['id'] ?>" 
                                                               value="<?= $option['id'] ?>">
                                                        <span class="option-text"><?= htmlspecialchars($option['option_text']) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="open-ended-question">
                                            <div class="form-group">
                                                <label>Metin yanıtınız:</label>
                                                <textarea class="form-control" 
                                                          name="answers[<?= $question['id'] ?>]" 
                                                          rows="4" 
                                                          placeholder="Cevabınızı buraya yazın..."></textarea>
                                            </div>
                                            
                                            <div class="divider">
                                                <span>veya</span>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>PDF dosyası yükleyin:</label>
                                                <div class="file-upload">
                                                    <input type="file" 
                                                           class="form-control" 
                                                           name="answer_file[<?= $question['id'] ?>]" 
                                                           accept=".pdf" 
                                                           id="file_<?= $question['id'] ?>">
                                                    <label for="file_<?= $question['id'] ?>" class="file-label">
                                                        <i class="bi bi-upload"></i>
                                                        <span>Dosya Seç</span>
                                                    </label>
                                                    <div class="file-info">Sadece PDF (Max 5MB)</div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="submit-section">
                        <button type="submit" class="btn btn-primary btn-submit">
                            <span>Başvuruyu Tamamla</span>
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.body.style.userSelect = 'none';
        
        const selectableElements = document.querySelectorAll('textarea, input[type="text"], input[type="email"]');
        selectableElements.forEach(element => {
            element.style.userSelect = 'text';
        });
        
        document.addEventListener('copy', function(e) {
            const selection = window.getSelection().toString();
            if (selection.length > 0) { 
                const activeElement = document.activeElement;
                if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        document.addEventListener('cut', function(e) {
             const activeElement = document.activeElement;
            if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                return false;
            }
        });
        
        document.addEventListener('paste', function(e) {
            const target = e.target;
            if (target.tagName === 'TEXTAREA' || (target.tagName === 'INPUT' && (target.type === 'text' || target.type === 'email' || target.type === 'search'))) {
                return true; 
            }
            e.preventDefault();
            return false;
        });
        
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'v') {
                const target = e.target;
                if (target.tagName === 'TEXTAREA' || (target.tagName === 'INPUT' && (target.type === 'text' || target.type === 'email' || target.type === 'search'))) {
                    return true; 
                }
            }
            
            const activeElement = document.activeElement;
            if (!(activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA')) {
                if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'c' || e.key.toLowerCase() === 'x' || e.key.toLowerCase() === 'a' || e.key.toLowerCase() === 's' || e.key.toLowerCase() === 'u')) {
                    e.preventDefault();
                    return false;
                }
            } else { 
                 if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'c' || e.key.toLowerCase() === 'x' )) {
                 } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'u') { 
                    e.preventDefault();
                    return false;
                 }
            }
            
            if (e.key === 'PrintScreen' || e.keyCode === 44) {
                e.preventDefault();
                return false;
            }
            
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && (e.key.toUpperCase() === 'I' || e.key.toUpperCase() === 'J' || e.key.toUpperCase() === 'C'))) {
                e.preventDefault();
                return false;
            }
        });
        
        document.addEventListener('contextmenu', function(e) {
            const target = e.target;
            if (target.tagName === 'TEXTAREA' || (target.tagName === 'INPUT' && (target.type === 'text' || target.type === 'email' || target.type === 'search'))) {
                return true;
            }
            e.preventDefault();
            return false;
        });
        
        document.addEventListener('selectstart', function(e) {
            const target = e.target;
            if (target.tagName !== 'TEXTAREA' && target.tagName !== 'INPUT') {
                e.preventDefault();
                return false;
            }
        });

        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                const fileLabel = this.nextElementSibling; 
                const icon = fileLabel.querySelector('i');
                const nameSpan = fileLabel.querySelector('span');
                const fileInfoDiv = fileLabel.nextElementSibling; 

                if (file) {
                    const fileName = file.name.length > 25 ? file.name.substring(0, 22) + '...' : file.name;
                    if (nameSpan) nameSpan.textContent = fileName;
                    if (icon) icon.className = 'bi bi-file-earmark-pdf-fill'; 
                    if (fileLabel) {
                        fileLabel.style.background = 'linear-gradient(135deg, var(--success-light) 0%, rgba(16, 185, 129, 0.05) 100%)';
                        fileLabel.style.borderColor = 'var(--success)';
                        fileLabel.style.color = 'var(--success)';
                    }
                    
                    if (fileInfoDiv) {
                        const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                        fileInfoDiv.textContent = `${file.name} (${fileSize} MB)`;
                        fileInfoDiv.style.color = 'var(--text-dark)'; 
                    }

                } else { 
                    if (nameSpan) nameSpan.textContent = 'Dosya Seç';
                    if (icon) icon.className = 'bi bi-upload';
                    if (fileLabel) {
                         fileLabel.style.background = 'linear-gradient(135deg, var(--primary-light) 0%, rgba(79, 70, 229, 0.1) 100%)';
                         fileLabel.style.borderColor = 'rgba(79, 70, 229, 0.3)';
                         fileLabel.style.color = 'var(--primary)';
                    }
                    if (fileInfoDiv) {
                        fileInfoDiv.textContent = 'Sadece PDF (Max 5MB)';
                        fileInfoDiv.style.color = 'var(--text-light)';
                    }
                }
            });
        });

        const totalTimeInSeconds = <?= $remaining_seconds ?>; 
        let timeLeft = totalTimeInSeconds;
        const timerElement = document.getElementById('timer');
        const countdownElement = document.getElementById('countdown-timer');
        const quizForm = document.getElementById('quiz-form');
        
        let countdownTimer = null;

        function updateTimer() {
            if (timeLeft <= 0) {
                clearInterval(countdownTimer);
                timerElement.innerHTML = `0:00`;
                countdownElement.classList.remove('warning');
                countdownElement.classList.add('danger');
                countdownElement.style.transform = 'scale(1)'; // Reset pulse transform
                if (quizForm && !quizForm.dataset.submitted && '<?= $_SERVER['REQUEST_METHOD'] ?>' === 'GET' && !('<?= $timeout_expired ?>' === '1')) { 
                   autoSubmitForm();
                }
                timeLeft = 0; // Ensure timeLeft doesn't go negative
                return;
            }
            
            const minutes = Math.floor(timeLeft / 60);
            let seconds = timeLeft % 60;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            
            timerElement.innerHTML = `${minutes}:${seconds}`;
            
            if (timeLeft <= 60) { 
                countdownElement.classList.remove('warning');
                countdownElement.classList.add('danger');
                if (timeLeft % 2 === 0) {
                    countdownElement.style.transform = 'scale(1.05)';
                } else {
                    countdownElement.style.transform = 'scale(1)';
                }
            } else if (timeLeft <= 300) { 
                countdownElement.classList.remove('warning');
                countdownElement.classList.add('danger');
                countdownElement.style.transform = 'scale(1)'; // Reset pulse transform
            } else if (timeLeft <= 600) { 
                countdownElement.classList.add('warning');
                countdownElement.classList.remove('danger');
                countdownElement.style.transform = 'scale(1)'; // Reset pulse transform
            } else {
                countdownElement.classList.remove('warning');
                countdownElement.classList.remove('danger');
                countdownElement.style.transform = 'scale(1)';
            }
            
            timeLeft--;
        }

        if (timerElement && countdownElement) { // Check if elements exist (e.g. not on success/timeout page)
            updateTimer(); // Call once immediately to set initial state

            if (timeLeft > 0 && !('<?= $timeout_expired ?>' === '1') && quizForm) { 
                 countdownTimer = setInterval(updateTimer, 1000);
            }
        }
        
        function autoSubmitForm() {
            if (!quizForm || quizForm.dataset.submitted) return; 
            quizForm.dataset.submitted = 'true'; 

            const notification = document.createElement('div');
            notification.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; width:100%; height:100%; background: rgba(0,0,0,0.7); 
                            display:flex; align-items:center; justify-content:center; z-index: 10000;">
                    <div style="background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                                text-align: center; border: 3px solid var(--danger);">
                        <i class="bi bi-clock-fill" style="font-size: 3rem; color: var(--danger); margin-bottom: 1rem; display:block;"></i>
                        <h3 style="margin-bottom: 1rem; color: var(--text-dark); font-weight:700;">Süre Doldu!</h3>
                        <p style="color: var(--text-light); margin-bottom:0;">Yanıtlarınız otomatik olarak gönderiliyor...</p>
                    </div>
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (quizForm) quizForm.submit();
            }, 2500); 
        }
        
        const optionCards = document.querySelectorAll('.option-card');
        optionCards.forEach((card) => {
            card.addEventListener('click', function() {
                const questionBody = this.closest('.question-body');
                if (!questionBody) return;
                const allCardsInQuestion = questionBody.querySelectorAll('.option-card');
                
                allCardsInQuestion.forEach(c => {
                    c.classList.remove('selected');
                    c.style.transform = ''; 
                });
                
                this.classList.add('selected');
                
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
                
                this.style.transform = 'translateX(8px) scale(1.01)';
                setTimeout(() => {
                    if (this.classList.contains('selected')) { 
                        this.style.transform = 'translateX(8px)';
                    }
                }, 150);
            });
            
            card.addEventListener('mouseenter', function() {
                if (!this.classList.contains('selected')) {
                    this.style.transform = 'translateX(4px)';
                }
            });
            
            card.addEventListener('mouseleave', function() {
                if (!this.classList.contains('selected')) {
                    this.style.transform = '';
                }
            });
        });
        
        document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            const card = radio.closest('.option-card');
            if (card) {
                card.classList.add('selected');
                card.style.transform = 'translateX(8px)'; 
            }
        });
        
        if (quizForm) {
            quizForm.addEventListener('submit', function(e) {
                this.dataset.submitted = 'true';
                this.dataset.submittedBy = 'user'; 
                
                const submitBtn = this.querySelector('.btn-submit');
                if (submitBtn) {
                    submitBtn.innerHTML = `
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <span style="margin-left: 0.5rem;">Gönderiliyor...</span>
                    `;
                    submitBtn.disabled = true;
                }
                
                const overlay = document.createElement('div');
                overlay.innerHTML = `
                    <div style="position: fixed; inset: 0; background: rgba(255,255,255,0.8); backdrop-filter: blur(5px);
                                display: flex; align-items: center; justify-content: center; z-index: 9999;">
                        <div style="background: var(--bg-white); padding: 2.5rem; border-radius: var(--radius-lg); text-align: center; box-shadow: var(--shadow-xl); border: 1px solid var(--border-light);">
                            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                            <h4 style="color:var(--text-dark); font-weight:600;">Başvurunuz İşleniyor</h4>
                            <p style="color: var(--text-light); margin: 0;">Lütfen bekleyiniz, bu işlem biraz sürebilir...</p>
                        </div>
                    </div>
                `;
                document.body.appendChild(overlay);
            });
        }
        
        window.addEventListener('beforeunload', function(e) {
            if (quizForm && quizForm.dataset.submitted === 'true') {
                return; 
            }
            
            const hasQuestions = <?= !empty($questions) ? 'true' : 'false' ?>;
            if (hasQuestions && timeLeft > 0 && quizForm) { // Ensure quizForm exists (not on success/timeout page)
                const confirmationMessage = 'Değerlendirme henüz tamamlanmadı. Sayfadan ayrılırsanız yanıtlarınız kaybolabilir. Ayrılmak istediğinizden emin misiniz?';
                e.returnValue = confirmationMessage;
                return confirmationMessage;
            }
        });
        
        let autoSaveTimerDraft;
        const formElementsForBackup = document.querySelectorAll('#quiz-form input[type="radio"], #quiz-form textarea');

        function saveDraft() {
            if (!quizForm) return;
            const answers = {};
            const formData = new FormData(quizForm);
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('answers[')) {
                    answers[key] = value;
                }
            }
            localStorage.setItem(`quiz_backup_<?= $application_id ?>_<?= $job_id ?>`, JSON.stringify(answers));
        }

        formElementsForBackup.forEach(el => {
            el.addEventListener('input', () => { 
                clearTimeout(autoSaveTimerDraft);
                autoSaveTimerDraft = setTimeout(saveDraft, 1500);
            });
            if (el.type === 'radio') {
                 el.addEventListener('change', () => {
                    clearTimeout(autoSaveTimerDraft);
                    autoSaveTimerDraft = setTimeout(saveDraft, 1500);
                });
            }
        });
        
        const backupData = localStorage.getItem(`quiz_backup_<?= $application_id ?>_<?= $job_id ?>`);
        if (backupData && !('<?= $success ?>' === '1' || '<?= $timeout_expired ?>' === '1') && quizForm) { 
            try {
                const answers = JSON.parse(backupData);
                Object.entries(answers).forEach(([key, value]) => {
                    const elements = document.querySelectorAll(`[name="${key}"]`);
                    elements.forEach(element => {
                        if (element.tagName === 'TEXTAREA') {
                            element.value = value;
                        } else if (element.type === 'radio' && element.value === value) {
                            element.checked = true;
                            const card = element.closest('.option-card');
                            if (card) {
                                 card.classList.add('selected');
                                 card.style.transform = 'translateX(8px)';
                            }
                        }
                    });
                });
            } catch (e) {
                console.warn('Backup data corrupted or error restoring:', e);
            }
        }
        
        if (quizForm) {
            quizForm.addEventListener('submit', function() {
                localStorage.removeItem(`quiz_backup_<?= $application_id ?>_<?= $job_id ?>`);
            });
        }
         if ('<?= $timeout_expired ?>' === '1' || '<?= $success ?>' === '1') { // Clear on timeout or success page as well
             localStorage.removeItem(`quiz_backup_<?= $application_id ?>_<?= $job_id ?>`);
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                const focusedElement = document.activeElement;
                if (focusedElement && focusedElement.closest('.option-card')) {
                    e.preventDefault();
                    const currentOptionCard = focusedElement.closest('.option-card');
                    const questionBody = currentOptionCard.closest('.question-body');
                    if (!questionBody) return;

                    const optionsInputs = Array.from(questionBody.querySelectorAll('.option-card input[type="radio"]'));
                    const currentIndex = optionsInputs.indexOf(focusedElement.querySelector('input[type="radio"]') || focusedElement);
                    
                    let nextIndex;
                    if (e.key === 'ArrowDown') {
                        nextIndex = (currentIndex + 1) % optionsInputs.length;
                    } else {
                        nextIndex = currentIndex === 0 ? optionsInputs.length - 1 : currentIndex - 1;
                    }
                    
                    if (optionsInputs[nextIndex]) {
                        optionsInputs[nextIndex].focus();
                        const nextLabel = optionsInputs[nextIndex].closest('.option-card');
                        if (nextLabel) nextLabel.click();
                    }
                }
            }
            
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'enter') {
                if (quizForm && !quizForm.dataset.submitted) {
                     if (confirm('Değerlendirmeyi şimdi tamamlayıp göndermek istediğinizden emin misiniz?')) {
                        quizForm.submit();
                    }
                }
            }
        });
        
        function markQuestionVisualCompletion(questionBox, isComplete) {
            const questionNumberEl = questionBox.querySelector('.question-number');
            if (isComplete) {
                questionBox.style.borderColor = 'var(--success)'; 
                if (questionNumberEl && !questionNumberEl.dataset.completed) {
                    questionNumberEl.style.background = 'linear-gradient(135deg, var(--success) 0%, #047857 100%)';
                    questionNumberEl.innerHTML = '<i class="bi bi-check-lg"></i>'; 
                    questionNumberEl.dataset.completed = 'true';
                }
            } else {
                 questionBox.style.borderColor = 'var(--border-light)'; 
            }
        }

        function checkQuestionCompletion(questionBox) {
            let isComplete = false;
            const radioChecked = questionBox.querySelector('input[type="radio"]:checked');
            const textarea = questionBox.querySelector('textarea');
            const fileInput = questionBox.querySelector('input[type="file"]');

            if (radioChecked) {
                isComplete = true;
            } else if (textarea && textarea.value.trim() !== '') {
                isComplete = true;
            } else if (fileInput && fileInput.files.length > 0) {
                isComplete = true;
            }
            markQuestionVisualCompletion(questionBox, isComplete);
        }
        
        document.querySelectorAll('.question-box').forEach(qBox => {
            const qNumEl = qBox.querySelector('.question-number');
            if (qNumEl) qNumEl.dataset.originalNumber = qNumEl.textContent;

            qBox.addEventListener('change', () => checkQuestionCompletion(qBox)); 
            const textarea = qBox.querySelector('textarea');
            if (textarea) {
                textarea.addEventListener('input', () => checkQuestionCompletion(qBox)); 
            }
            checkQuestionCompletion(qBox); 
        });
        
        console.log('Quiz initialized.');
    });
</script>
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
IGNORE_WHEN_COPYING_END
</body>
</html>

<?php
if (isset($db) && $db->inTransaction()) {
$db->rollBack();
}
require_once 'includes/footer.php';
ob_end_flush();
?>
    