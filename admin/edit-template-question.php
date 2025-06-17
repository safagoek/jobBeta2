<?php
// Output buffering başlat - header yönlendirme sorununu çözer
ob_start();

session_start();

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/db.php';

// Soru ID ve şablon ID kontrolü
$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

if ($question_id <= 0 || $template_id <= 0) {
    header('Location: manage-templates.php');
    exit;
}

// Soruyu ve şablon bilgisini al
$stmt = $db->prepare("SELECT q.*, t.template_name 
                     FROM template_questions q
                     JOIN question_templates t ON q.template_id = t.id
                     WHERE q.id = :question_id AND q.template_id = :template_id");
$stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
$stmt->bindParam(':template_id', $template_id, PDO::PARAM_INT);
$stmt->execute();
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    header('Location: edit-template.php?id=' . $template_id);
    exit;
}

// Sorununun şıklarını al
$options = [];
if ($question['question_type'] === 'multiple_choice') {
    $stmt = $db->prepare("SELECT * FROM template_options WHERE question_id = :question_id ORDER BY id");
    $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
    $stmt->execute();
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$success = '';
$error = '';

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_text = trim($_POST['question_text']);
    
    if (empty($question_text)) {
        $error = "Soru metni boş olamaz.";
    } else {
        try {
            $db->beginTransaction();
            
            // Soruyu güncelle
            $stmt = $db->prepare("UPDATE template_questions SET question_text = :question_text WHERE id = :question_id");
            $stmt->bindParam(':question_text', $question_text);
            $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Çoktan seçmeli soru ise şıkları güncelle
            if ($question['question_type'] === 'multiple_choice') {
                // Mevcut şıkları sil
                $stmt = $db->prepare("DELETE FROM template_options WHERE question_id = :question_id");
                $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
                $stmt->execute();
                
                // Yeni şıkları ekle
                if (isset($_POST['options']) && is_array($_POST['options'])) {
                    $correct_option = isset($_POST['correct_option']) ? (int)$_POST['correct_option'] : -1;
                    
                    $options = [];
                    foreach ($_POST['options'] as $index => $option_text) {
                        if (!empty(trim($option_text))) {
                            $is_correct = ($index == $correct_option) ? 1 : 0;
                            
                            $stmt = $db->prepare("INSERT INTO template_options (question_id, option_text, is_correct) 
                                                VALUES (:question_id, :option_text, :is_correct)");
                            $stmt->bindParam(':question_id', $question_id, PDO::PARAM_INT);
                            $stmt->bindParam(':option_text', $option_text);
                            $stmt->bindParam(':is_correct', $is_correct, PDO::PARAM_BOOL);
                            $stmt->execute();
                            
                            // Yeni eklenen şıkları kaydet (gösterim için)
                            $options[] = [
                                'id' => $db->lastInsertId(),
                                'option_text' => $option_text,
                                'is_correct' => $is_correct
                            ];
                        }
                    }
                }
            }
            
            $db->commit();
            $success = "Soru başarıyla güncellendi.";
            
            // Güncel soru bilgilerini yükle
            $question['question_text'] = $question_text;
            
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şablon Sorusu Düzenle - İş Başvuru Sistemi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .correct-option {
            background-color: #d1e7dd;
            border-color: #badbcc;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Soru Düzenle</h1>
            <a href="edit-template.php?id=<?= $template_id ?>" class="btn btn-secondary">Şablona Dön</a>
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
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Şablon: <?= htmlspecialchars($question['template_name']) ?></h5>
            </div>
            <div class="card-body">
                <form method="post" id="questionForm">
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Soru Metni *</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required><?= htmlspecialchars($question['question_text']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Soru Tipi</label>
                        <p class="form-control-static">
                            <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                <span class="badge bg-primary">Çoktan Seçmeli</span>
                            <?php else: ?>
                                <span class="badge bg-success">Açık Uçlu</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-muted small">Soru tipi değiştirilemez. Farklı tipte bir soru için yeni soru ekleyin.</p>
                    </div>
                    
                    <?php if ($question['question_type'] === 'multiple_choice'): ?>
                        <div id="optionsSection">
                            <div class="mb-3">
                                <label class="form-label">Şıklar (En az 2 şık eklemelisiniz) *</label>
                                <p class="text-muted small">Doğru cevabı radyo butonuyla işaretleyin</p>
                                
                                <div id="optionsContainer">
                                    <?php foreach ($options as $index => $option): ?>
                                        <div class="input-group mb-2">
                                            <div class="input-group-text">
                                                <input type="radio" name="correct_option" value="<?= $index ?>" 
                                                       <?= ($option['is_correct']) ? 'checked' : '' ?> class="form-check-input mt-0">
                                            </div>
                                            <input type="text" class="form-control <?= ($option['is_correct']) ? 'correct-option' : '' ?>" 
                                                   name="options[]" value="<?= htmlspecialchars($option['option_text']) ?>" required>
                                            <?php if (count($options) > 2): ?>
                                                <button class="btn btn-outline-danger" type="button" onclick="this.parentNode.remove(); updateOptionIndices();">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addOption()">
                                    <i class="bi bi-plus-circle"></i> Şık Ekle
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                        <a href="edit-template.php?id=<?= $template_id ?>" class="btn btn-secondary ms-2">İptal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let optionCount = <?= count($options) ?>;
        
        function addOption() {
            const container = document.getElementById('optionsContainer');
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `
                <div class="input-group-text">
                    <input type="radio" name="correct_option" value="${optionCount}" class="form-check-input mt-0">
                </div>
                <input type="text" class="form-control" name="options[]" placeholder="Şık ${optionCount + 1}" required>
                <button class="btn btn-outline-danger" type="button" onclick="this.parentNode.remove(); updateOptionIndices();">
                    <i class="bi bi-trash"></i>
                </button>
            `;
            container.appendChild(div);
            optionCount++;
            updateOptionIndices();
        }
        
        function updateOptionIndices() {
            const container = document.getElementById('optionsContainer');
            const options = container.querySelectorAll('.input-group');
            options.forEach((option, index) => {
                const radio = option.querySelector('input[type="radio"]');
                radio.value = index;
                const input = option.querySelector('input[type="text"]');
                if (!input.value) {
                    input.placeholder = `Şık ${index + 1}`;
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Form gönderildiğinde şık kontrolü
            document.getElementById('questionForm').addEventListener('submit', function(e) {
                <?php if ($question['question_type'] === 'multiple_choice'): ?>
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
                <?php endif; ?>
            });
            
            // Radio butonlarına tıklandığında CSS class'ını güncelle
            const radioButtons = document.querySelectorAll('input[name="correct_option"]');
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    // Tüm şıkların arka plan renklerini sıfırla
                    const allOptions = document.querySelectorAll('input[name="options[]"]');
                    allOptions.forEach(option => {
                        option.classList.remove('correct-option');
                    });
                    
                    // Seçilen şıkkın arka plan rengini ayarla
                    if (this.checked) {
                        const inputField = this.closest('.input-group').querySelector('input[type="text"]');
                        inputField.classList.add('correct-option');
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php
// Output buffer içeriğini gönder ve buffer'ı temizle
ob_end_flush();
?>