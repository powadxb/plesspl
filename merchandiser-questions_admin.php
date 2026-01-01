<?php
session_start();
$page_title = 'Merchandiser Questions';
require 'php/bootstrap.php';

// Ensure session is active and user is admin
if (!isset($_SESSION['dins_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['dins_user_id'];
$user_details = $DB->query("SELECT * FROM users WHERE id = ?", [$user_id])[0];

// Ensure only admins can access this page
if ($user_details['admin'] < 1) {
    header('Location: no_access.php');
    exit;
}

require 'assets/header.php';

// Handle answer submission
$message = '';
if ($_POST && isset($_POST['submit_answer'])) {
    $question_id = intval($_POST['question_id']);
    $answer = trim($_POST['answer']);
    $status = $_POST['status'];
    
    if (!empty($answer) && $question_id > 0) {
        $DB->query("UPDATE merchandiser_questions SET answer = ?, status = ?, answered_at = NOW(), answered_by = ? WHERE id = ?", 
                   [$answer, $status, $user_id, $question_id]);
        $message = 'Answer submitted successfully!';
    }
}

// Handle status change
if ($_POST && isset($_POST['change_status'])) {
    $question_id = intval($_POST['question_id']);
    $status = $_POST['status'];
    
    if ($question_id > 0) {
        $DB->query("UPDATE merchandiser_questions SET status = ? WHERE id = ?", [$status, $question_id]);
        $message = 'Status updated successfully!';
    }
}

// Fetch questions with user details
$questions = $DB->query("
    SELECT mq.*, u.username 
    FROM merchandiser_questions mq 
    LEFT JOIN users u ON mq.user_id = u.id 
    ORDER BY mq.created_at DESC
");
?>

<div class="page-wrapper">
    <?php require 'assets/navbar.php' ?>
    
    <div class="page-content--bgf7">
        <section class="welcome p-t-10">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h1 class="title-4"><?=$page_title?></h1>
                        <hr class="line-seprate">
                    </div>
                </div>
            </div>
        </section>

        <?php if (!empty($message)): ?>
        <section class="p-t-10">
            <div class="container">
                <div class="alert alert-success"><?= $message ?></div>
            </div>
        </section>
        <?php endif; ?>

        <section class="p-t-20">
            <div class="container">
                <div class="row">
                    <?php if (empty($questions)): ?>
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <h4><i class="fas fa-info-circle"></i> No Questions Yet</h4>
                            <p>No merchandiser questions have been submitted yet.</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($questions as $question): ?>
                    <div class="col-md-12 mb-4">
                        <div class="card question-card status-<?= $question['status'] ?>">
                            <div class="card-header">
                                <div class="question-header">
                                    <div class="question-meta">
                                        <span class="status-badge status-<?= $question['status'] ?>"><?= ucfirst($question['status']) ?></span>
                                        <strong><?= htmlspecialchars($question['username']) ?></strong>
                                        <span class="text-muted">• <?= date('M j, Y g:i A', strtotime($question['created_at'])) ?></span>
                                        <?php if (!empty($question['product_sku'])): ?>
                                        <span class="sku-badge">SKU: <?= htmlspecialchars($question['product_sku']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="question-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                            <select name="status" class="form-control form-control-sm status-select" onchange="this.form.submit()">
                                                <option value="pending" <?= $question['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="answered" <?= $question['status'] == 'answered' ? 'selected' : '' ?>>Answered</option>
                                                <option value="closed" <?= $question['status'] == 'closed' ? 'selected' : '' ?>>Closed</option>
                                            </select>
                                            <input type="hidden" name="change_status" value="1">
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="question-content">
                                    <h5>Question:</h5>
                                    <p><?= nl2br(htmlspecialchars($question['question'])) ?></p>
                                </div>
                                
                                <?php if (!empty($question['answer'])): ?>
                                <div class="answer-content">
                                    <h5>Answer:</h5>
                                    <p><?= nl2br(htmlspecialchars($question['answer'])) ?></p>
                                    <small class="text-muted">
                                        Answered on <?= date('M j, Y g:i A', strtotime($question['answered_at'])) ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($question['status'] != 'closed'): ?>
                                <div class="answer-form">
                                    <form method="POST">
                                        <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                        <div class="form-group">
                                            <label for="answer_<?= $question['id'] ?>">Your Answer:</label>
                                            <textarea class="form-control" name="answer" id="answer_<?= $question['id'] ?>" rows="3" placeholder="Type your answer here..."><?= htmlspecialchars($question['answer'] ?? '') ?></textarea>
                                        </div>
                                        <div class="form-row">
                                            <div class="col-md-6">
                                                <select name="status" class="form-control">
                                                    <option value="answered">Mark as Answered</option>
                                                    <option value="closed">Mark as Closed</option>
                                                    <option value="pending">Keep as Pending</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <button type="submit" name="submit_answer" class="btn btn-primary">
                                                    <i class="fas fa-reply"></i> Submit Answer
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require 'assets/footer.php'; ?>

<style>
.question-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-pending {
    border-left: 4px solid #ffc107;
}

.status-answered {
    border-left: 4px solid #28a745;
}

.status-closed {
    border-left: 4px solid #6c757d;
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.question-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.status-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.status-pending {
    background-color: #fff3cd;
    color: #856404;
}

.status-badge.status-answered {
    background-color: #d4edda;
    color: #155724;
}

.status-badge.status-closed {
    background-color: #f8f9fa;
    color: #6c757d;
}

.sku-badge {
    background-color: #e9ecef;
    color: #495057;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-select {
    width: auto;
    display: inline-block;
    min-width: 120px;
}

.question-content {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.answer-content {
    background-color: #e8f5e8;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.answer-form {
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 10px 15px;
}

.card-body {
    padding: 15px;
}

.form-control-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border-radius: 0.2rem;
}

.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .question-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .question-actions {
        width: 100%;
    }
    
    .status-select {
        width: 100%;
    }
}
</style>