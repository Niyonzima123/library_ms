<?php
$page_title = 'Reports & Analytics';
require_once 'includes/admin_header.php';

$csrf_token = generateCSRFToken();

// Report type definitions
$report_types = [
    'inventory' => [
        'icon' => 'bi-book',
        'color' => 'primary',
        'label' => 'Books Inventory',
        'desc' => 'Complete catalog with stock levels',
        'needs_category' => true,
    ],
    'issued' => [
        'icon' => 'bi-journal-arrow-up',
        'color' => 'info',
        'label' => 'Issued Books',
        'desc' => 'All book issue transactions',
        'needs_dept' => true,
    ],
    'overdue' => [
        'icon' => 'bi-exclamation-triangle',
        'color' => 'danger',
        'label' => 'Overdue / Defaulters',
        'desc' => 'Books past their due date',
        'needs_dept' => true,
    ],
    'registrations' => [
        'icon' => 'bi-person-plus',
        'color' => 'success',
        'label' => 'Student Registrations',
        'desc' => 'New student sign-ups over time',
        'needs_dept' => true,
    ],
    'category_stats' => [
        'icon' => 'bi-tags',
        'color' => 'warning',
        'label' => 'Category-wise Stats',
        'desc' => 'Popularity and stock by category',
    ],
    'department_stats' => [
        'icon' => 'bi-building',
        'color' => 'secondary',
        'label' => 'Department-wise Stats',
        'desc' => 'Borrowing patterns by department',
    ],
    'monthly_circulation' => [
        'icon' => 'bi-graph-up',
        'color' => 'dark',
        'label' => 'Monthly Circulation',
        'desc' => 'Issue and return trends per month',
    ],
    'librarian_activity' => [
        'icon' => 'bi-activity',
        'color' => 'info',
        'label' => 'Librarian Activity',
        'desc' => 'Actions performed by librarians',
    ],
];

$selected_type = isset($_GET['type']) && array_key_exists($_GET['type'], $report_types) ? $_GET['type'] : '';

// Fetch filter options
$departments = [];
$dept_stmt = $conn->prepare("SELECT dept_id, dept_name FROM departments ORDER BY dept_name");
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
while ($r = $dept_result->fetch_assoc()) $departments[] = $r;
$dept_stmt->close();

$categories = [];
$cat_stmt = $conn->prepare("SELECT cat_id, cat_name FROM categories ORDER BY cat_name");
$cat_stmt->execute();
$cat_result = $cat_stmt->get_result();
while ($r = $cat_result->fetch_assoc()) $categories[] = $r;
$cat_stmt->close();

// Filter values
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$filter_dept = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
$filter_cat = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;

// Report data containers
$report_data = [];
$report_summary = [];
$chart_labels = [];
$chart_data = [];
$chart_type = 'bar';
$report_title = '';
$report_table_headers = [];
$has_data = false;

// Generate report if type is selected
if ($selected_type) {
    // Mark overdue books first
    $conn->query("UPDATE issued_books SET status = " . ISSUE_OVERDUE . " WHERE status = " . ISSUE_APPROVED . " AND due_date < CURDATE()");

    switch ($selected_type) {

        // ── BOOKS INVENTORY ──────────────────────────────────────────────
        case 'inventory':
            $report_title = 'Books Inventory Report';
            $report_table_headers = ['#', 'Book No', 'Title', 'Author', 'Category', 'Price', 'Total Copies', 'Available', 'Issued', 'Status', 'Rack'];

            $where = [];
            $params = [];
            $types = '';
            if ($filter_cat > 0) {
                $where[] = 'b.cat_id = ?';
                $params[] = $filter_cat;
                $types .= 'i';
            }
            $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT b.*, a.author_name, c.cat_name,
                           (b.total_copies - b.available_copies) AS issued_count
                    FROM books b
                    LEFT JOIN authors a ON b.author_id = a.author_id
                    LEFT JOIN categories c ON b.cat_id = c.cat_id
                    {$where_sql}
                    ORDER BY b.book_name";
            $stmt = $conn->prepare($sql);
            if ($types) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Summary
            $total_books = count($report_data);
            $total_copies = array_sum(array_column($report_data, 'total_copies'));
            $total_available = array_sum(array_column($report_data, 'available_copies'));
            $total_issued = array_sum(array_column($report_data, 'issued_count'));
            $total_value = array_sum(array_map(fn($r) => $r['book_price'] * $r['total_copies'], $report_data));
            $report_summary = [
                ['label' => 'Total Titles', 'value' => $total_books, 'icon' => 'bi-book', 'color' => 'blue'],
                ['label' => 'Total Copies', 'value' => $total_copies, 'icon' => 'bi-stack', 'color' => 'purple'],
                ['label' => 'Available', 'value' => $total_available, 'icon' => 'bi-check-circle', 'color' => 'green'],
                ['label' => 'Currently Issued', 'value' => $total_issued, 'icon' => 'bi-journal-arrow-up', 'color' => 'orange'],
                ['label' => 'Inventory Value', 'value' => '$' . number_format($total_value, 2), 'icon' => 'bi-currency-dollar', 'color' => 'cyan'],
            ];

            // Chart: category distribution
            $cat_counts = [];
            foreach ($report_data as $r) {
                $cat = $r['cat_name'] ?? 'Uncategorized';
                $cat_counts[$cat] = ($cat_counts[$cat] ?? 0) + (int)$r['total_copies'];
            }
            $chart_labels = array_keys($cat_counts);
            $chart_data = array_values($cat_counts);
            $chart_type = 'doughnut';
            $has_data = !empty($report_data);
            break;

        // ── ISSUED BOOKS ─────────────────────────────────────────────────
        case 'issued':
            $report_title = 'Issued Books Report';
            $report_table_headers = ['#', 'Student', 'Student ID', 'Book', 'Issue Date', 'Due Date', 'Return Date', 'Status', 'Fine', 'Issued By'];

            $where = ['ib.issue_date BETWEEN ? AND ?'];
            $params = [$date_from, $date_to];
            $types = 'ss';
            if ($filter_dept > 0) {
                $where[] = 'u.dept_id = ?';
                $params[] = $filter_dept;
                $types .= 'i';
            }
            $where_sql = 'WHERE ' . implode(' AND ', $where);

            $sql = "SELECT ib.*, b.book_name, b.book_no,
                           u.name AS student_name, u.student_id,
                           d.dept_name,
                           a.name AS issued_by_name
                    FROM issued_books ib
                    JOIN books b ON ib.book_id = b.book_id
                    JOIN users u ON ib.user_id = u.id
                    LEFT JOIN departments d ON u.dept_id = d.dept_id
                    LEFT JOIN admins a ON ib.issued_by = a.id
                    {$where_sql}
                    ORDER BY ib.issue_date DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $total_issued = count($report_data);
            $returned = count(array_filter($report_data, fn($r) => $r['status'] == ISSUE_RETURNED));
            $pending = count(array_filter($report_data, fn($r) => $r['status'] == ISSUE_APPROVED || $r['status'] == ISSUE_PENDING));
            $overdue = count(array_filter($report_data, fn($r) => $r['status'] == ISSUE_OVERDUE));
            $total_fine = array_sum(array_column($report_data, 'fine_amount'));

            $report_summary = [
                ['label' => 'Total Issues', 'value' => $total_issued, 'icon' => 'bi-journal-bookmark', 'color' => 'blue'],
                ['label' => 'Returned', 'value' => $returned, 'icon' => 'bi-journal-check', 'color' => 'green'],
                ['label' => 'Pending', 'value' => $pending, 'icon' => 'bi-hourglass-split', 'color' => 'orange'],
                ['label' => 'Overdue', 'value' => $overdue, 'icon' => 'bi-exclamation-triangle', 'color' => 'red'],
                ['label' => 'Total Fines', 'value' => '$' . number_format($total_fine, 2), 'icon' => 'bi-currency-dollar', 'color' => 'cyan'],
            ];

            // Chart: daily issues
            $daily = [];
            foreach ($report_data as $r) {
                $d = $r['issue_date'];
                $daily[$d] = ($daily[$d] ?? 0) + 1;
            }
            ksort($daily);
            $chart_labels = array_map(fn($d) => date('M d', strtotime($d)), array_keys($daily));
            $chart_data = array_values($daily);
            $chart_type = 'bar';
            $has_data = !empty($report_data);
            break;

        // ── OVERDUE / DEFAULTERS ─────────────────────────────────────────
        case 'overdue':
            $report_title = 'Overdue & Defaulters Report';
            $report_table_headers = ['#', 'Student', 'Student ID', 'Department', 'Book', 'Issue Date', 'Due Date', 'Days Overdue', 'Fine', 'Fine Status'];

            $where = ['ib.status IN (?, ?)', 'ib.due_date < CURDATE()'];
            $params = [ISSUE_APPROVED, ISSUE_OVERDUE];
            $types = 'ii';
            if ($filter_dept > 0) {
                $where[] = 'u.dept_id = ?';
                $params[] = $filter_dept;
                $types .= 'i';
            }
            $where_sql = 'WHERE ' . implode(' AND ', $where);

            $sql = "SELECT ib.issue_id, ib.issue_date, ib.due_date, ib.fine_amount, ib.fine_paid, ib.status,
                           b.book_name, b.isbn,
                           u.name AS student_name, u.student_id, u.email, u.mobile,
                           d.dept_name
                    FROM issued_books ib
                    JOIN books b ON ib.book_id = b.book_id
                    JOIN users u ON ib.user_id = u.id
                    LEFT JOIN departments d ON u.dept_id = d.dept_id
                    {$where_sql}
                    ORDER BY ib.due_date ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Enrich with days overdue and calculated fine
            $total_fine = 0;
            $defaulter_set = [];
            foreach ($report_data as &$r) {
                $r['days_overdue'] = max(0, floor((time() - strtotime($r['due_date'])) / 86400));
                $r['calculated_fine'] = $r['days_overdue'] * FINE_PER_DAY;
                $r['actual_fine'] = max($r['calculated_fine'], $r['fine_amount']);
                $total_fine += $r['actual_fine'];
                $defaulter_set[$r['issue_id']] = true;
            }
            unset($r);

            $report_summary = [
                ['label' => 'Total Defaulters', 'value' => count($defaulter_set), 'icon' => 'bi-people-fill', 'color' => 'red'],
                ['label' => 'Overdue Books', 'value' => count($report_data), 'icon' => 'bi-journal-x', 'color' => 'orange'],
                ['label' => 'Unpaid Fines', 'value' => count(array_filter($report_data, fn($r) => !$r['fine_paid'])), 'icon' => 'bi-cash-stack', 'color' => 'red'],
                ['label' => 'Total Fine Amount', 'value' => '$' . number_format($total_fine, 2), 'icon' => 'bi-currency-dollar', 'color' => 'cyan'],
            ];

            // Chart: top 10 overdue days
            $sorted = $report_data;
            usort($sorted, fn($a, $b) => $b['days_overdue'] - $a['days_overdue']);
            $top10 = array_slice($sorted, 0, 10);
            $chart_labels = array_map(fn($r) => mb_substr($r['student_name'], 0, 15), $top10);
            $chart_data = array_column($top10, 'days_overdue');
            $chart_type = 'bar';
            $has_data = !empty($report_data);
            break;

        // ── MONTHLY CIRCULATION ──────────────────────────────────────────
        case 'monthly_circulation':
            $report_title = 'Monthly Circulation Report';
            $report_table_headers = ['#', 'Month', 'Issues', 'Returns', 'Net Issued'];

            // Determine date range: default last 12 months
            $range_from = $date_from ?: date('Y-m-d', strtotime('-12 months'));
            $range_to = $date_to ?: date('Y-m-d');

            $sql = "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS month_key,
                           DATE_FORMAT(issue_date, '%b %Y') AS month_label,
                           COUNT(*) AS total_issues,
                           SUM(CASE WHEN status = " . ISSUE_RETURNED . " OR return_date IS NOT NULL THEN 1 ELSE 0 END) AS total_returns
                    FROM issued_books
                    WHERE issue_date BETWEEN ? AND ?
                    GROUP BY YEAR(issue_date), MONTH(issue_date)
                    ORDER BY YEAR(issue_date), MONTH(issue_date)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $range_from, $range_to);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $total_issues = array_sum(array_column($report_data, 'total_issues'));
            $total_returns = array_sum(array_column($report_data, 'total_returns'));
            $avg_per_month = count($report_data) > 0 ? round($total_issues / count($report_data), 1) : 0;
            $peak_issues = count($report_data) > 0 ? max(array_column($report_data, 'total_issues')) : 0;

            $report_summary = [
                ['label' => 'Total Issues', 'value' => $total_issues, 'icon' => 'bi-journal-arrow-up', 'color' => 'blue'],
                ['label' => 'Total Returns', 'value' => $total_returns, 'icon' => 'bi-journal-arrow-down', 'color' => 'green'],
                ['label' => 'Avg Issues/Month', 'value' => $avg_per_month, 'icon' => 'bi-graph-up', 'color' => 'purple'],
                ['label' => 'Peak Month Issues', 'value' => $peak_issues, 'icon' => 'bi-trophy', 'color' => 'orange'],
            ];

            $chart_labels = array_column($report_data, 'month_label');
            $chart_issues = array_column($report_data, 'total_issues');
            $chart_returns = array_column($report_data, 'total_returns');
            $chart_type = 'line';
            $has_data = !empty($report_data);
            break;

        // ── STUDENT REGISTRATIONS ────────────────────────────────────────
        case 'registrations':
            $report_title = 'Student Registrations Report';
            $report_table_headers = ['#', 'Name', 'Student ID', 'Email', 'Department', 'Class', 'Status', 'Registered On'];

            $where = ['u.created_at BETWEEN ? AND ?'];
            $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
            $types = 'ss';
            if ($filter_dept > 0) {
                $where[] = 'u.dept_id = ?';
                $params[] = $filter_dept;
                $types .= 'i';
            }
            $where_sql = 'WHERE ' . implode(' AND ', $where);

            $sql = "SELECT u.name, u.student_id, u.email, u.approval_status, u.created_at,
                           d.dept_name, c.class_name
                    FROM users u
                    LEFT JOIN departments d ON u.dept_id = d.dept_id
                    LEFT JOIN classes c ON u.class_id = c.class_id
                    {$where_sql}
                    ORDER BY u.created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $total_reg = count($report_data);
            $approved = count(array_filter($report_data, fn($r) => $r['approval_status'] === 'approved'));
            $pending = count(array_filter($report_data, fn($r) => $r['approval_status'] === 'pending'));
            $rejected = count(array_filter($report_data, fn($r) => $r['approval_status'] === 'rejected'));

            $report_summary = [
                ['label' => 'Total Registrations', 'value' => $total_reg, 'icon' => 'bi-people', 'color' => 'blue'],
                ['label' => 'Approved', 'value' => $approved, 'icon' => 'bi-check-circle', 'color' => 'green'],
                ['label' => 'Pending', 'value' => $pending, 'icon' => 'bi-hourglass-split', 'color' => 'orange'],
                ['label' => 'Rejected', 'value' => $rejected, 'icon' => 'bi-x-circle', 'color' => 'red'],
            ];

            // Chart: registrations per day
            $daily = [];
            foreach ($report_data as $r) {
                $d = date('Y-m-d', strtotime($r['created_at']));
                $daily[$d] = ($daily[$d] ?? 0) + 1;
            }
            ksort($daily);
            $chart_labels = array_map(fn($d) => date('M d', strtotime($d)), array_keys($daily));
            $chart_data = array_values($daily);
            $chart_type = 'bar';
            $has_data = !empty($report_data);
            break;

        // ── CATEGORY-WISE STATS ──────────────────────────────────────────
        case 'category_stats':
            $report_title = 'Category-wise Statistics';
            $report_table_headers = ['#', 'Category', 'Total Titles', 'Total Copies', 'Available Copies', 'Times Issued', 'Utilization %'];

            $sql = "SELECT c.cat_id, c.cat_name,
                           COUNT(DISTINCT b.book_id) AS total_titles,
                           COALESCE(SUM(b.total_copies), 0) AS total_copies,
                           COALESCE(SUM(b.available_copies), 0) AS available_copies,
                           COUNT(DISTINCT ib.issue_id) AS times_issued
                    FROM categories c
                    LEFT JOIN books b ON c.cat_id = b.cat_id
                    LEFT JOIN issued_books ib ON b.book_id = ib.book_id
                    GROUP BY c.cat_id, c.cat_name
                    ORDER BY times_issued DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($report_data as &$r) {
                $r['utilization'] = $r['total_copies'] > 0 ? round(($r['times_issued'] / $r['total_copies']) * 100, 1) : 0;
            }
            unset($r);

            $total_titles = array_sum(array_column($report_data, 'total_titles'));
            $total_copies = array_sum(array_column($report_data, 'total_copies'));
            $total_issued = array_sum(array_column($report_data, 'times_issued'));

            $report_summary = [
                ['label' => 'Total Categories', 'value' => count($report_data), 'icon' => 'bi-tags', 'color' => 'blue'],
                ['label' => 'Total Titles', 'value' => $total_titles, 'icon' => 'bi-book', 'color' => 'purple'],
                ['label' => 'Total Copies', 'value' => $total_copies, 'icon' => 'bi-stack', 'color' => 'cyan'],
                ['label' => 'Total Issues', 'value' => $total_issued, 'icon' => 'bi-journal-bookmark', 'color' => 'orange'],
            ];

            $chart_labels = array_column($report_data, 'cat_name');
            $chart_data = array_column($report_data, 'times_issued');
            $chart_type = 'doughnut';
            $has_data = !empty($report_data);
            break;

        // ── DEPARTMENT-WISE STATS ────────────────────────────────────────
        case 'department_stats':
            $report_title = 'Department-wise Statistics';
            $report_table_headers = ['#', 'Department', 'Students', 'Books Issued', 'Overdue Books', 'Fines Collected', 'Avg Fine'];

            $sql = "SELECT d.dept_id, d.dept_name,
                           COUNT(DISTINCT u.id) AS total_students,
                           COUNT(DISTINCT ib.issue_id) AS books_issued,
                           SUM(CASE WHEN ib.status = 3 THEN 1 ELSE 0 END) AS overdue_count,
                           COALESCE(SUM(CASE WHEN ib.fine_paid = 1 THEN ib.fine_amount ELSE 0 END), 0) AS fines_collected
                    FROM departments d
                    LEFT JOIN users u ON d.dept_id = u.dept_id AND u.approval_status = 'approved'
                    LEFT JOIN issued_books ib ON u.id = ib.user_id
                    GROUP BY d.dept_id, d.dept_name
                    ORDER BY books_issued DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($report_data as &$r) {
                $r['avg_fine'] = $r['books_issued'] > 0 ? round($r['fines_collected'] / max($r['overdue_count'], 1), 2) : 0;
            }
            unset($r);

            $total_students = array_sum(array_column($report_data, 'total_students'));
            $total_issued = array_sum(array_column($report_data, 'books_issued'));

            $report_summary = [
                ['label' => 'Departments', 'value' => count($report_data), 'icon' => 'bi-building', 'color' => 'blue'],
                ['label' => 'Total Students', 'value' => $total_students, 'icon' => 'bi-people', 'color' => 'green'],
                ['label' => 'Total Issues', 'value' => $total_issued, 'icon' => 'bi-journal-bookmark', 'color' => 'purple'],
            ];

            $chart_labels = array_column($report_data, 'dept_name');
            $chart_data = array_column($report_data, 'books_issued');
            $chart_type = 'bar';
            $has_data = !empty($report_data);
            break;

        // ── LIBRARIAN ACTIVITY ───────────────────────────────────────────
        case 'librarian_activity':
            $report_title = 'Librarian Activity Report';
            $report_table_headers = ['#', 'Librarian', 'Action', 'Description', 'IP Address', 'Date & Time'];

            $where = ['al.user_type = ?', 'al.created_at BETWEEN ? AND ?'];
            $params = ['admin', $date_from . ' 00:00:00', $date_to . ' 23:59:59'];
            $types = 'sss';

            $where_sql = 'WHERE ' . implode(' AND ', $where);

            $sql = "SELECT al.*, a.name AS admin_name, a.role AS admin_role
                    FROM activity_log al
                    LEFT JOIN admins a ON al.user_id = a.id
                    {$where_sql}
                    ORDER BY al.created_at DESC
                    LIMIT 500";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $actions = [];
            foreach ($report_data as $r) {
                $act = $r['action'];
                $actions[$act] = ($actions[$act] ?? 0) + 1;
            }
            arsort($actions);

            $report_summary = [
                ['label' => 'Total Actions', 'value' => count($report_data), 'icon' => 'bi-activity', 'color' => 'blue'],
                ['label' => 'Unique Actions', 'value' => count($actions), 'icon' => 'bi-list-check', 'color' => 'purple'],
                ['label' => 'Top Action', 'value' => !empty($actions) ? array_key_first($actions) : 'N/A', 'icon' => 'bi-star', 'color' => 'orange'],
            ];

            // Chart: top 8 actions
            $top_actions = array_slice($actions, 0, 8, true);
            $chart_labels = array_keys($top_actions);
            $chart_data = array_values($top_actions);
            $chart_type = 'bar';
            $has_data = !empty($report_data);
            break;
    }

    // ── SAVE TO REPORTS HISTORY ─────────────────────────────────────────
    if ($has_data) {
        $log_params = json_encode([
            'type' => $selected_type,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'dept_id' => $filter_dept,
            'cat_id' => $filter_cat,
        ]);
        $hist_stmt = $conn->prepare("INSERT INTO reports_history (report_type, report_format, report_title, parameters, generated_by, generated_by_type) VALUES (?, 'pdf', ?, ?, ?, ?)");
        $generated_by = $_SESSION['user_id'];
        $generated_by_type = $_SESSION['role'] === ROLE_ADMIN ? 'admin' : 'librarian';
        $hist_stmt->bind_param('ssiss', $selected_type, $report_title, $log_params, $generated_by, $generated_by_type);
        $hist_stmt->execute();
        $hist_stmt->close();
    }
}

// Chart colors
$chart_colors = ['#4361ee', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#7c3aed', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#8b5cf6', '#0ea5e9'];
?>

<!-- Print CSS -->
<style>
    @media print {
        .sidebar, .top-navbar, .main-footer, .no-print,
        .report-selector, .filters-bar, .page-header-actions,
        .btn, nav.breadcrumb, .sidebar-toggle, .navbar-actions,
        .navbar-search, .dashboard-wrapper > nav {
            display: none !important;
        }
        .dashboard-wrapper {
            display: block !important;
        }
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .page-content {
            padding: 0 !important;
        }
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .table-responsive {
            overflow: visible !important;
        }
        .chart-container {
            max-height: 300px !important;
        }
        body {
            background: #fff !important;
            font-size: 11pt;
        }
        .page-header h4 {
            font-size: 18pt;
        }
        .report-print-header {
            display: block !important;
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .report-print-header h3 {
            margin: 0;
            font-size: 16pt;
        }
        .report-print-header p {
            margin: 2px 0;
            font-size: 10pt;
            color: #666;
        }
    }
    .report-print-header {
        display: none;
    }
    .report-selector {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .report-card {
        background: #fff;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 1.25rem;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
        cursor: pointer;
        display: block;
    }
    .report-card:hover {
        border-color: #4361ee;
        box-shadow: 0 4px 15px rgba(67, 97, 238, 0.15);
        transform: translateY(-2px);
    }
    .report-card.active {
        border-color: #4361ee;
        background: linear-gradient(135deg, #f0f3ff 0%, #e8ecff 100%);
    }
    .report-card .report-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        margin-bottom: 0.75rem;
    }
    .report-card .report-icon.bg-primary { background: #e8ecff; color: #4361ee; }
    .report-card .report-icon.bg-info { background: #e0f7fa; color: #0dcaf0; }
    .report-card .report-icon.bg-danger { background: #fde8e8; color: #ef4444; }
    .report-card .report-icon.bg-success { background: #e8f5e9; color: #10b981; }
    .report-card .report-icon.bg-warning { background: #fff8e1; color: #f59e0b; }
    .report-card .report-icon.bg-secondary { background: #f1f3f5; color: #6c757d; }
    .report-card .report-icon.bg-dark { background: #e9ecef; color: #212529; }
    .report-card h6 {
        margin: 0 0 0.25rem;
        font-weight: 600;
        font-size: 0.95rem;
    }
    .report-card p {
        margin: 0;
        font-size: 0.8rem;
        color: #6c757d;
    }
    .filters-bar .form-control,
    .filters-bar .form-select {
        font-size: 0.875rem;
    }
</style>

<!-- Print header (only visible when printing) -->
<div class="report-print-header">
    <h3><?php echo $report_title ?: 'Library Report'; ?></h3>
    <p><?php echo SITE_NAME; ?></p>
    <p>Generated on: <?php echo date('M d, Y h:i A'); ?></p>
    <?php if ($date_from && $date_to): ?>
        <p>Period: <?php echo formatDate($date_from); ?> - <?php echo formatDate($date_to); ?></p>
    <?php endif; ?>
</div>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-file-earmark-bar-graph me-2"></i>Reports & Analytics</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Reports<?php echo $selected_type ? ' &raquo; ' . sanitize($report_types[$selected_type]['label']) : ''; ?></li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions no-print">
        <?php if ($selected_type): ?>
            <a href="<?php echo ADMIN_URL; ?>/reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>All Reports</a>
        <?php endif; ?>
    </div>
</div>

<!-- Report Selector Cards -->
<?php if (!$selected_type): ?>
<div class="report-selector no-print">
    <?php foreach ($report_types as $key => $rt): ?>
        <a href="<?php echo ADMIN_URL; ?>/reports.php?type=<?php echo $key; ?>" class="report-card">
            <div class="report-icon bg-<?php echo $rt['color']; ?>">
                <i class="bi <?php echo $rt['icon']; ?>"></i>
            </div>
            <h6><?php echo sanitize($rt['label']); ?></h6>
            <p><?php echo sanitize($rt['desc']); ?></p>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($selected_type): ?>

<!-- Filter Form -->
<div class="card mb-3 no-print">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end" id="filterForm">
            <input type="hidden" name="type" value="<?php echo $selected_type; ?>">
            <div class="col-md-2">
                <label class="form-label fw-semibold">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo sanitize($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo sanitize($date_to); ?>">
            </div>

            <?php if (!empty($report_types[$selected_type]['needs_dept']) && !empty($departments)): ?>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Department</label>
                <select name="dept_id" class="form-select">
                    <option value="0">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['dept_id']; ?>" <?php echo $filter_dept == $d['dept_id'] ? 'selected' : ''; ?>><?php echo sanitize($d['dept_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if (!empty($report_types[$selected_type]['needs_category']) && !empty($categories)): ?>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Category</label>
                <select name="cat_id" class="form-select">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['cat_id']; ?>" <?php echo $filter_cat == $c['cat_id'] ? 'selected' : ''; ?>><?php echo sanitize($c['cat_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Generate</button>
            </div>
        </form>
    </div>
</div>

<?php if ($has_data): ?>

<!-- Action Buttons -->
<div class="d-flex gap-2 mb-3 no-print">
    <button class="btn btn-outline-primary" onclick="window.print();"><i class="bi bi-printer me-1"></i>Print</button>
    <button class="btn btn-outline-success" id="btnExportCSV"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</button>
    <button class="btn btn-outline-info" id="btnExportExcel"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</button>
</div>

<!-- Summary Stats -->
<div class="stats-grid mb-4">
    <?php foreach ($report_summary as $s): ?>
    <div class="stat-card">
        <div class="stat-icon <?php echo $s['color']; ?>"><i class="bi <?php echo $s['icon']; ?>"></i></div>
        <div class="stat-info">
            <h3><?php echo $s['value']; ?></h3>
            <p><?php echo sanitize($s['label']); ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Data Table -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-table me-2"></i><?php echo sanitize($report_title); ?></h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" id="reportTable">
                <thead>
                    <tr>
                        <?php foreach ($report_table_headers as $h): ?>
                            <th><?php echo $h; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $row_num = 1;
                    foreach ($report_data as $row):
                    ?>
                    <tr>
                        <?php
                        switch ($selected_type):
                            case 'inventory': ?>
                                <td><?php echo $row_num++; ?></td>
                                <td><?php echo sanitize($row['book_no']); ?></td>
                                <td><?php echo sanitize($row['book_name']); ?></td>
                                <td><?php echo sanitize($row['author_name'] ?? 'N/A'); ?></td>
                                <td><?php echo sanitize($row['cat_name'] ?? 'N/A'); ?></td>
                                <td>$<?php echo number_format($row['book_price'], 2); ?></td>
                                <td><?php echo $row['total_copies']; ?></td>
                                <td><span class="badge bg-success"><?php echo $row['available_copies']; ?></span></td>
                                <td><?php echo $row['issued_count']; ?></td>
                                <td><span class="status-badge <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                <td><?php echo sanitize($row['rack_location'] ?? '-'); ?></td>
                                <?php break;

                            case 'issued':
                                $status_labels = [
                                    ISSUE_PENDING => ['label' => 'Pending', 'class' => 'bg-warning text-dark'],
                                    ISSUE_APPROVED => ['label' => 'Approved', 'class' => 'bg-primary'],
                                    ISSUE_RETURNED => ['label' => 'Returned', 'class' => 'bg-success'],
                                    ISSUE_OVERDUE => ['label' => 'Overdue', 'class' => 'bg-danger'],
                                ];
                                $si = $status_labels[$row['status']] ?? ['label' => 'Unknown', 'class' => 'bg-secondary'];
                                ?>
                                <td><?php echo $row_num++; ?></td>
                                <td><?php echo sanitize($row['student_name']); ?></td>
                                <td><?php echo sanitize($row['student_id']); ?></td>
                                <td><?php echo sanitize($row['book_name']); ?> <small class="text-muted">#<?php echo sanitize($row['book_no']); ?></small></td>
                                <td><?php echo formatDate($row['issue_date']); ?></td>
                                <td><?php echo formatDate($row['due_date']); ?></td>
                                <td><?php echo $row['return_date'] ? formatDate($row['return_date']) : '<span class="text-muted">-</span>'; ?></td>
                                <td><span class="status-badge <?php echo $si['class']; ?>"><?php echo $si['label']; ?></span></td>
                                <td><?php echo $row['fine_amount'] > 0 ? '$' . number_format($row['fine_amount'], 2) : '-'; ?></td>
                                <td><?php echo sanitize($row['issued_by_name'] ?? 'N/A'); ?></td>
                                <?php break;

                            case 'overdue': ?>
                                <td><?php echo $row_num++; ?></td>
                                <td><strong><?php echo sanitize($row['student_name']); ?></strong><br><small class="text-muted"><?php echo sanitize($row['email']); ?></small></td>
                                <td><?php echo sanitize($row['student_id']); ?></td>
                                <td><?php echo sanitize($row['dept_name'] ?? 'N/A'); ?></td>
                                <td><strong><?php echo sanitize($row['book_name']); ?></strong><br><small class="text-muted"><?php echo sanitize($row['isbn'] ?? ''); ?></small></td>
                                <td><?php echo formatDate($row['issue_date']); ?></td>
                                <td><?php echo formatDate($row['due_date']); ?></td>
                                <td><span class="badge bg-danger"><?php echo $row['days_overdue']; ?> days</span></td>
                                <td class="fw-bold text-danger">$<?php echo number_format($row['actual_fine'], 2); ?></td>
                                <td><?php echo $row['fine_paid'] ? '<span class="badge bg-success">Paid</span>' : '<span class="badge bg-danger">Unpaid</span>'; ?></td>
                                <?php break;

                            case 'monthly_circulation': ?>
                                <td><?php echo $row_num++; ?></td>
                                <td><strong><?php echo sanitize($row['month_label']); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo $row['total_issues']; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $row['total_returns']; ?></span></td>
                                <td><?php echo $row['total_issues'] - $row['total_returns']; ?></td>
                                <?php break;

                            case 'registrations': ?>
                                <td><?php echo $row_num++; ?></td>
                                <td><?php echo sanitize($row['name']); ?></td>
                                <td><?php echo sanitize($row['student_id']); ?></td>
                                <td><?php echo sanitize($row['email']); ?></td>
                                <td><?php echo sanitize($row['dept_name'] ?? 'N/A'); ?></td>
                                <td><?php echo sanitize($row['class_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge bg-<?php echo $row['approval_status'] === 'approved' ? 'success' : ($row['approval_status'] === 'pending' ? 'warning text-dark' : 'danger'); ?>"><?php echo ucfirst($row['approval_status']); ?></span></td>
                                <td><?php echo formatDateTime($row['created_at']); ?></td>
                                <?php break;

                            case 'category_stats': ?>
                                <td><?php echo $row_num++; ?></td>
                                <td><strong><?php echo sanitize($row['cat_name']); ?></strong></td>
                                <td><?php echo $row['total_titles']; ?></td>
                                <td><?php echo $row['total_copies']; ?></td>
                                <td><span class="badge bg-success"><?php echo $row['available_copies']; ?></span></td>
                                <td><?php echo $row['times_issued']; ?></td>
                                <td>
                                    <div class="progress" style="height: 20px; min-width: 80px;">
                                        <div class="progress-bar bg-<?php echo $row['utilization'] > 70 ? 'danger' : ($row['utilization'] > 40 ? 'warning' : 'success'); ?>" style="width: <?php echo min($row['utilization'], 100); ?>%"><?php echo $row['utilization']; ?>%</div>
                                    </div>
                                </td>
                                <?php break;

                            case 'department_stats': ?>
                                <td><?php echo $row_num++; ?></td>
                                <td><strong><?php echo sanitize($row['dept_name']); ?></strong></td>
                                <td><?php echo $row['total_students']; ?></td>
                                <td><?php echo $row['books_issued']; ?></td>
                                <td><span class="badge bg-danger"><?php echo $row['overdue_count']; ?></span></td>
                                <td>$<?php echo number_format($row['fines_collected'], 2); ?></td>
                                <td>$<?php echo number_format($row['avg_fine'], 2); ?></td>
                                <?php break;

                            case 'librarian_activity': ?>
                                <td><?php echo $row_num++; ?></td>
                                <td><?php echo sanitize($row['admin_name'] ?? 'Unknown'); ?> <small class="text-muted">(<?php echo ucfirst($row['admin_role'] ?? ''); ?>)</small></td>
                                <td><span class="badge bg-<?php echo $row['user_type'] === 'admin' ? 'primary' : 'info'; ?>"><?php echo sanitize($row['action']); ?></span></td>
                                <td><?php echo sanitize(mb_strimwidth($row['description'] ?? '', 0, 80, '...')); ?></td>
                                <td><small class="text-muted"><?php echo sanitize($row['ip_address'] ?? '-'); ?></small></td>
                                <td><?php echo formatDateTime($row['created_at']); ?></td>
                                <?php break;

                        endswitch;
                        ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart -->
<?php if (!empty($chart_labels)): ?>
<div class="card mb-4 no-print">
    <div class="card-header">
        <h5><i class="bi bi-bar-chart me-2"></i><?php echo sanitize($report_title); ?> - Chart</h5>
    </div>
    <div class="card-body">
        <div class="chart-container" style="height: 350px;">
            <canvas id="reportChart"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- No Data State -->
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
            <h5 class="mt-3">No Data Found</h5>
            <p class="text-muted">No records match the selected filters. Try adjusting the date range or other criteria.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
$extra_js = '';

if ($selected_type && $has_data && !empty($chart_labels)) {
    $chart_bg_colors = json_encode(array_slice($chart_colors, 0, count($chart_labels)));

    if ($selected_type === 'monthly_circulation') {
        // Line chart with two datasets
        $extra_js = '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var ctx = document.getElementById("reportChart");
            if (ctx) {
                new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: ' . json_encode($chart_labels) . ',
                        datasets: [{
                            label: "Issues",
                            data: ' . json_encode($chart_issues) . ',
                            borderColor: "#4361ee",
                            backgroundColor: "rgba(67, 97, 238, 0.1)",
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }, {
                            label: "Returns",
                            data: ' . json_encode($chart_returns) . ',
                            borderColor: "#10b981",
                            backgroundColor: "rgba(16, 185, 129, 0.1)",
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: "top" } },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
        });
        </script>';
    } elseif ($chart_type === 'doughnut') {
        $extra_js = '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var ctx = document.getElementById("reportChart");
            if (ctx) {
                new Chart(ctx, {
                    type: "doughnut",
                    data: {
                        labels: ' . json_encode($chart_labels) . ',
                        datasets: [{
                            data: ' . json_encode($chart_data) . ',
                            backgroundColor: ' . $chart_bg_colors . ',
                            borderWidth: 2,
                            borderColor: "#fff"
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: "right", labels: { padding: 12, usePointStyle: true } }
                        }
                    }
                });
            }
        });
        </script>';
    } else {
        $extra_js = '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var ctx = document.getElementById("reportChart");
            if (ctx) {
                new Chart(ctx, {
                    type: "bar",
                    data: {
                        labels: ' . json_encode($chart_labels) . ',
                        datasets: [{
                            label: "' . addslashes($report_title) . '",
                            data: ' . json_encode(array_map("intval", $chart_data)) . ',
                            backgroundColor: ' . $chart_bg_colors . ',
                            borderWidth: 1,
                            borderRadius: 6,
                            barThickness: 30
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
        });
        </script>';
    }
}

// CSV & Excel export scripts
$extra_js .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    // CSV Export
    var btnCSV = document.getElementById("btnExportCSV");
    if (btnCSV) {
        btnCSV.addEventListener("click", function() {
            var table = document.getElementById("reportTable");
            if (!table) return;
            var rows = table.querySelectorAll("tr");
            var csv = [];
            for (var i = 0; i < rows.length; i++) {
                var cols = rows[i].querySelectorAll("th, td");
                var row = [];
                for (var j = 0; j < cols.length; j++) {
                    var text = cols[j].innerText.replace(/\\n/g, " ").replace(/\\s+/g, " ").trim();
                    // Escape quotes and wrap in quotes
                    if (text.indexOf(",") !== -1 || text.indexOf("\"") !== -1 || text.indexOf("\\n") !== -1) {
                        text = "\"" + text.replace(/"/g, "\"\"") + "\"";
                    }
                    row.push(text);
                }
                csv.push(row.join(","));
            }
            var csvContent = csv.join("\\n");
            var blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
            var link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "' . ($selected_type ? $selected_type : 'report') . '_report_" + new Date().toISOString().slice(0, 10) + ".csv";
            link.click();
        });
    }

    // Excel Export (HTML table to XLS)
    var btnExcel = document.getElementById("btnExportExcel");
    if (btnExcel) {
        btnExcel.addEventListener("click", function() {
            var table = document.getElementById("reportTable");
            if (!table) return;
            var html = "<html><head><meta charset=\"UTF-8\"><style>td,th{border:1px solid #ccc;padding:5px;} th{background:#4361ee;color:#fff;}</style></head><body>";
            html += table.outerHTML;
            html += "</body></html>";
            var blob = new Blob([html], { type: "application/vnd.ms-excel" });
            var link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "' . ($selected_type ? $selected_type : 'report') . '_report_" + new Date().toISOString().slice(0, 10) + ".xls";
            link.click();
        });
    }
});
</script>';

require_once 'includes/admin_footer.php';
?>
