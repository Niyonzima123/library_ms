<?php
$page_title = 'Reading';
include 'includes/student_header.php';
$user_id = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

$ebook_id = (int)($_GET['id'] ?? 0);
$book_id = (int)($_GET['book_id'] ?? 0);

if ($ebook_id <= 0 && $book_id <= 0) {
    setFlashMessage('danger', 'Invalid book ID.');
    redirect(STUDENT_URL . '/catalog.php');
}

// Fetch ebook and book info
if ($ebook_id > 0) {
    $stmt = $conn->prepare("SELECT eb.*, b.book_name, b.book_id, b.cover_image, a.author_name 
        FROM ebooks eb 
        JOIN books b ON eb.book_id = b.book_id 
        LEFT JOIN authors a ON b.author_id = a.author_id 
        WHERE eb.ebook_id = ?");
    $stmt->bind_param("i", $ebook_id);
    $stmt->execute();
    $ebook = $stmt->get_result()->fetch_assoc();
    if (!$ebook) {
        setFlashMessage('danger', 'E-book not found.');
        redirect(STUDENT_URL . '/catalog.php');
    }
    $book_id = $ebook['book_id'];
} else {
    $stmt = $conn->prepare("SELECT b.*, a.author_name FROM books b LEFT JOIN authors a ON b.author_id = a.author_id WHERE b.book_id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $ebook = $stmt->get_result()->fetch_assoc();
    $ebook['file_path'] = '';
}

// Increment view count
$conn->query("UPDATE books SET view_count = view_count + 1 WHERE book_id = " . (int)$book_id);
if (isset($ebook['ebook_id'])) {
    $conn->query("UPDATE ebooks SET view_count = view_count + 1 WHERE ebook_id = " . (int)$ebook['ebook_id']);
}

// Handle note actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(STUDENT_URL . '/read_book.php?id=' . $ebook_id . '&book_id=' . $book_id);
    }
    if ($action === 'add_reading_note') {
        $note_title = trim($_POST['note_title'] ?? '');
        $note_content = trim($_POST['note_content'] ?? '');
        if (!empty($note_title)) {
            $stmt = $conn->prepare("INSERT INTO student_notes (user_id, book_id, note_title, note_content, note_type, color, is_pinned, is_archived) VALUES (?, ?, ?, ?, 'book_note', '#d1ecf1', 0, 0)");
            $stmt->bind_param("iiss", $user_id, $book_id, $note_title, $note_content);
            $stmt->execute();
            setFlashMessage('success', 'Note saved.');
        }
        redirect(STUDENT_URL . '/read_book.php?id=' . $ebook_id . '&book_id=' . $book_id);
    }
    if ($action === 'delete_reading_note') {
        $note_id = (int)($_POST['note_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM student_notes WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $note_id, $user_id);
        $stmt->execute();
        setFlashMessage('success', 'Note deleted.');
        redirect(STUDENT_URL . '/read_book.php?id=' . $ebook_id . '&book_id=' . $book_id);
    }
}

// Fetch notes for this book
$stmt = $conn->prepare("SELECT * FROM student_notes WHERE user_id = ? AND book_id = ? ORDER BY is_pinned DESC, created_at DESC");
$stmt->bind_param("ii", $user_id, $book_id);
$stmt->execute();
$book_notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<style>
.reading-layout { display: flex; height: calc(100vh - 120px); gap: 0; }
.book-panel { flex: 1; overflow: auto; background: #f8f9fa; border-radius: 8px; padding: 20px; }
.notes-panel { width: 380px; overflow-y: auto; background: white; border-left: 2px solid #e0e0e0; padding: 15px; }
.note-card { background: #f8f9fa; border-radius: 8px; padding: 12px; margin-bottom: 10px; border-left: 4px solid #0d6efd; }
.note-card.pinned { border-left-color: #ffc107; }
.ebook-frame { width: 100%; height: 100%; border: none; border-radius: 8px; }
@media (max-width: 768px) { .reading-layout { flex-direction: column; } .notes-panel { width: 100%; height: 50%; border-left: none; border-top: 2px solid #e0e0e0; } }
</style>

<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo STUDENT_URL; ?>/catalog.php">Catalog</a></li>
                <li class="breadcrumb-item"><a href="<?php echo STUDENT_URL; ?>/book_details.php?id=<?php echo $book_id; ?>"><?php echo sanitize($ebook['book_name']); ?></a></li>
                <li class="breadcrumb-item active">Reading</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions">
        <div class="btn-group">
            <button class="btn btn-outline-primary" onclick="toggleNotes()"><i class="bi bi-journal-text me-1"></i>Toggle Notes</button>
            <a href="<?php echo STUDENT_URL; ?>/notes.php" class="btn btn-outline-secondary"><i class="bi bi-journal me-1"></i>All Notes</a>
        </div>
    </div>
</div>

<div class="reading-layout" id="readingLayout">
    <!-- Left: Book Content -->
    <div class="book-panel" id="bookPanel">
        <div class="d-flex align-items-center mb-3 p-3 bg-white rounded shadow-sm">
            <?php if (!empty($ebook['cover_image'])): ?>
                <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $ebook['cover_image']; ?>" alt="" style="width:40px;height:55px;object-fit:cover;border-radius:4px;margin-right:12px;">
            <?php endif; ?>
            <div>
                <h5 class="mb-0"><?php echo sanitize($ebook['book_name']); ?></h5>
                <small class="text-muted"><?php echo sanitize($ebook['author_name'] ?? 'Unknown Author'); ?></small>
            </div>
        </div>
        
        <?php if (!empty($ebook['file_path'])): ?>
            <iframe src="<?php echo SITE_URL . '/' . $ebook['file_path']; ?>" class="ebook-frame" style="height:calc(100vh - 250px);"></iframe>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-file-earmark-x" style="font-size:4rem;color:#ccc;"></i>
                <h5 class="mt-3 text-muted">No E-Book File Available</h5>
                <p class="text-muted">This book does not have an e-book file uploaded. You can still take notes while reading a physical copy.</p>
                <a href="<?php echo STUDENT_URL; ?>/book_details.php?id=<?php echo $book_id; ?>" class="btn btn-primary">View Book Details</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Notes Panel -->
    <div class="notes-panel" id="notesPanel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="bi bi-journal-text me-1"></i> My Notes (<?php echo count($book_notes); ?>)</h6>
        </div>

        <!-- Quick Add Note -->
        <div class="card mb-3" style="border:2px dashed #0d6efd;">
            <div class="card-body p-2">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add_reading_note">
                    <input type="text" class="form-control form-control-sm mb-2" name="note_title" placeholder="Note title..." required>
                    <textarea class="form-control form-control-sm mb-2" name="note_content" rows="3" placeholder="Write your note here..."></textarea>
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-save me-1"></i>Save Note</button>
                </form>
            </div>
        </div>

        <!-- Notes List -->
        <?php if (empty($book_notes)): ?>
            <div class="text-center py-4">
                <i class="bi bi-journal-x text-muted" style="font-size:2rem;"></i>
                <p class="text-muted mt-2">No notes for this book yet.<br>Start taking notes above!</p>
            </div>
        <?php else: ?>
            <?php foreach ($book_notes as $note): ?>
                <div class="note-card <?php echo $note['is_pinned'] ? 'pinned' : ''; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <strong style="font-size:0.88rem;"><?php echo sanitize($note['note_title']); ?></strong>
                        <?php if ($note['is_pinned']): ?>
                            <i class="bi bi-pin-fill text-warning" title="Pinned"></i>
                        <?php endif; ?>
                    </div>
                    <p class="mb-1" style="font-size:0.82rem;white-space:pre-wrap;max-height:100px;overflow:hidden;"><?php echo sanitize($note['note_content']); ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted"><?php echo formatDate($note['created_at']); ?></small>
                        <div>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this note?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="delete_reading_note">
                                <input type="hidden" name="note_id" value="<?php echo $note['note_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleNotes() {
    const panel = document.getElementById('notesPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : (panel.offsetWidth > 0 ? 'none' : 'block');
}
</script>

<?php include 'includes/student_footer.php'; ?>
