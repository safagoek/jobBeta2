<?php
session_start();

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/db.php';

// İş ilanı ID'si kontrolü
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
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

$success = '';
$error = '';

// Soru silme işlemi
if (isset($_GET['delete_question']) && is_numeric($_GET['delete_question'])) {
    $question_id = (int)$_GET['delete_question'];
    
    try {
        $db->beginTransaction();
        
        // Soruyu sil
        $stmt = $db->prepare("DELETE FROM questions WHERE id = :question_id AND job_id = :job_id");
        $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
        $stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $db->commit();
        $success = "Soru başarıyla silindi.";
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Soru silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Yeni soru ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_question') {
    $question_text = trim($_POST['question_text']);
    $question_type = $_POST['question_type'];
    
    if (empty($question_text)) {
        $error = "Soru metni boş olamaz.";
    } else {
        try {
            $db->beginTransaction();
            
            // Soruyu ekle
            $stmt = $db->prepare("INSERT INTO questions (job_id, question_text, question_type) 
                                 VALUES (:job_id, :question_text, :question_type)");
            $stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
            $stmt->bindParam(':question_text', $question_text);
            $stmt->bindParam(':question_type', $question_type);
            $stmt->execute();
            
            $question_id = $db->lastInsertId();
            
            // Çoktan seçmeli soru ise şıkları ekle
            if ($question_type === 'multiple_choice' && isset($_POST['options']) && is_array($_POST['options'])) {
                $correct_option = isset($_POST['correct_option']) ? (int)$_POST['correct_option'] : -1;
                
                foreach ($_POST['options'] as $index => $option_text) {
                    if (!empty(trim($option_text))) {
                        $is_correct = ($index == $correct_option) ? 1 : 0;
                        
                        $stmt = $db->prepare("INSERT INTO options (question_id, option_text, is_correct) 
                                            VALUES (:question_id, :option_text, :is_correct)");
                        $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
                        $stmt->bindParam(':option_text', $option_text);
                        $stmt->bindParam(':is_correct', $is_correct, PDO::PARAM_BOOL);
                        $stmt->execute();
                    }
                }
            }
            
            $db->commit();
            $success = "Yeni soru başarıyla eklendi.";
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Soru eklenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// İş ilanına ait soruları al
$stmt = $db->prepare("SELECT * FROM questions WHERE job_id = :job_id ORDER BY id");
$stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Her sorunun şıklarını al
$question_options = [];
foreach ($questions as $question) {
    if ($question['question_type'] == 'multiple_choice') {
        $stmt = $db->prepare("SELECT * FROM options WHERE question_id = :question_id");
        $stmt->bindParam(':question_id', $question['id'], PDO::PARAM_INT);
        $stmt->execute();
        $question_options[$question['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İş İlanı Soruları - İş Başvuru Sistemi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .question-card {
            margin-bottom: 25px;
            border-left: 4px solid #0d6efd;
        }
        .question-card.open-ended {
            border-left-color: #20c997;
        }
        .correct-option {
            background-color: #d1e7dd;
            border-color: #badbcc;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

<style>
    .admin-navbar {
        background-color: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 12px 0;
    }
    
    .admin-navbar .navbar-brand {
        font-weight: 600;
        color: #333;
        display: flex;
        align-items: center;
    }
    
    .admin-navbar .brand-icon {
        color: #4361ee;
        margin-right: 8px;
    }
    
    .admin-navbar .nav-link {
        color: #495057;
        padding: 0.5rem 0.8rem;
        border-radius: 6px;
        margin-right: 5px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        font-weight: 500;
    }
    
    .admin-navbar .nav-link i {
        margin-right: 6px;
        font-size: 1.1em;
    }
    
    .admin-navbar .nav-link:hover {
        color: #4361ee;
        background-color: #f8f9fa;
    }
    
    .admin-navbar .nav-link.active {
        color: #4361ee;
        background-color: #ebefff;
        font-weight: 600;
    }
    
    .admin-navbar .logout-link {
        color: #6c757d;
    }
    
    .admin-navbar .logout-link:hover {
        color: #dc3545;
        background-color: rgba(220, 53, 69, 0.1);
    }
    
    @media (max-width: 992px) {
        .admin-navbar .navbar-nav {
            padding-top: 15px;
        }
        
        .admin-navbar .nav-link {
            padding: 10px;
            margin-bottom: 5px;
        }
    }
</style>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>İş İlanı Soruları</h1>
            <a href="edit-job.php?id=<?= $job_id ?>" class="btn btn-secondary">İş İlanına Dön</a>
        </div>
        
        <div class="alert alert-info mb-4">
            <h4 class="alert-heading"><?= htmlspecialchars($job['title']) ?></h4>
            <p class="mb-0">Bu iş ilanı için soruları düzenleyebilirsiniz.</p>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Yeni Soru Ekle -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Yeni Soru Ekle</h5>
                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#addQuestionForm">
                    <i class="bi bi-plus-lg"></i> Yeni Soru
                </button>
            </div>
            <div class="card-body collapse" id="addQuestionForm">
                <form method="post" id="questionForm">
                    <input type="hidden" name="action" value="add_question">
                    
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Soru Metni *</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Soru Tipi *</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="question_type" id="type_multiple_choice" value="multiple_choice" checked onchange="toggleOptionsSection()">
                            <label class="form-check-label" for="type_multiple_choice">
                                Çoktan Seçmeli
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="question_type" id="type_open_ended" value="open_ended" onchange="toggleOptionsSection()">
                            <label class="form-check-label" for="type_open_ended">
                                Açık Uçlu
                            </label>
                        </div>
                    </div>
                    
                    <div id="optionsSection">
                        <div class="mb-3">
                            <label class="form-label">Şıklar (En az 2 şık eklemelisiniz) *</label>
                            <p class="text-muted small">Doğru cevabı radyo butonuyla işaretleyin</p>
                            
                            <div id="optionsContainer">
                                <div class="input-group mb-2">
                                    <div class="input-group-text">
                                        <input type="radio" name="correct_option" value="0" checked class="form-check-input mt-0">
                                    </div>
                                    <input type="text" class="form-control" name="options[]" placeholder="Şık 1" required>
                                </div>
                                <div class="input-group mb-2">
                                    <div class="input-group-text">
                                        <input type="radio" name="correct_option" value="1" class="form-check-input mt-0">
                                    </div>
                                    <input type="text" class="form-control" name="options[]" placeholder="Şık 2" required>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addOption()">
                                <i class="bi bi-plus-circle"></i> Şık Ekle
                            </button>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Soruyu Kaydet</button>
                        <button type="button" class="btn btn-secondary ms-2" data-bs-toggle="collapse" data-bs-target="#addQuestionForm">İptal</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Mevcut Sorular -->
        <h2 class="mb-3">Mevcut Sorular (<?= count($questions) ?>)</h2>
        
        <?php if (empty($questions)): ?>
            <div class="alert alert-warning">Bu iş ilanı için henüz soru bulunmamaktadır.</div>
        <?php else: ?>
            <?php foreach ($questions as $index => $question): ?>
                <div class="card question-card <?= ($question['question_type'] == 'open_ended') ? 'open-ended' : '' ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <?php if ($question['question_type'] == 'multiple_choice'): ?>
                                <span class="badge bg-primary me-2"><?= $index + 1 ?></span>
                            <?php else: ?>
                                <span class="badge bg-success me-2"><?= $index + 1 ?></span>
                            <?php endif; ?>
                            <?= htmlspecialchars($question['question_text']) ?>
                        </h5>
                        <div>
                            <a href="edit-question.php?id=<?= $question['id'] ?>&job_id=<?= $job_id ?>" class="btn btn-sm btn-warning">
                                <i class="bi bi-pencil"></i> Düzenle
                            </a>
                            <a href="manage-job-questions.php?job_id=<?= $job_id ?>&delete_question=<?= $question['id'] ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Bu soruyu silmek istediğinizden emin misiniz?')">
                                <i class="bi bi-trash"></i> Sil
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($question['question_type'] == 'multiple_choice'): ?>
                            <p><strong>Soru Tipi:</strong> Çoktan Seçmeli</p>
                            <p><strong>Şıklar:</strong></p>
                            <ul class="list-group">
                                <?php foreach ($question_options[$question['id']] as $option): ?>
                                    <li class="list-group-item <?= ($option['is_correct']) ? 'correct-option' : '' ?>">
                                        <?= htmlspecialchars($option['option_text']) ?>
                                        <?php if ($option['is_correct']): ?>
                                            <span class="badge bg-success float-end">Doğru Cevap</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p><strong>Soru Tipi:</strong> Açık Uçlu</p>
                            <p class="text-muted">Bu soru için adaylar metin veya dosya yükleyerek cevap vereceklerdir.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let optionCount = 2;
        
        function toggleOptionsSection() {
            const isMultipleChoice = document.getElementById('type_multiple_choice').checked;
            document.getElementById('optionsSection').style.display = isMultipleChoice ? 'block' : 'none';
        }
        
        function addOption() {
            const container = document.getElementById('optionsContainer');
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `
                <div class="input-group-text">
                    <input type="radio" name="correct_option" value="${optionCount}" class="form-check-input mt-0">
                </div>
                <input type="text" class="form-control" name="options[]" placeholder="Şık ${optionCount + 1}" required>
                <button class="btn btn-outline-danger" type="button" onclick="this.parentNode.remove()">
                    <i class="bi bi-trash"></i>
                </button>
            `;
            container.appendChild(div);
            optionCount++;
        }
        
        // Form gönderildiğinde şık kontrolü
        document.getElementById('questionForm').addEventListener('submit', function(e) {
            const isMultipleChoice = document.getElementById('type_multiple_choice').checked;
            
            if (isMultipleChoice) {
                const options = document.querySelectorAll('input[name="options[]"]');
                let filledOptions = 0;
                
                options.forEach(option => {
                    if (option.value.trim() !== '') {
                        filledOptions++;
                    }
                });
                
                if (filledOptions < 2) {
                    e.preventDefault();
                    alert('En az 2 şık eklemelisiniz.');
                    return false;
                }
                
                // Doğru cevap işaretlenmiş mi kontrol et
                const correctOption = document.querySelector('input[name="correct_option"]:checked');
                if (!correctOption) {
                    e.preventDefault();
                    alert('Lütfen doğru cevabı işaretleyin.');
                    return false;
                }
            }
        });
    </script>
</body>
</html>