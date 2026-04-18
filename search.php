<?php
session_start();
require 'db.php';
$pdo = getDB();

$q = $_GET['q'] ?? '';
$q = trim($q);

if ($q === '') {
    die("Please enter a search term.");
}

$stmt = $conn->prepare("
    SELECT *
    FROM patients
    WHERE full_name LIKE ?
       OR id LIKE ?
       OR phone LIKE ?
");

$search = "%$q%";
$stmt->bind_param("sss", $search, $search, $search);
$stmt->execute();

$result = $stmt->get_result();
?>

<h2>Search Results for:
    <?= htmlspecialchars($q) ?>
</h2>

<?php if ($result->num_rows > 0): ?>
    <ul>
        <?php while ($row = $result->fetch_assoc()): ?>
            <li>
                <b>
                    <?= htmlspecialchars($row['full_name']) ?>
                </b>
                (ID:
                <?= $row['id'] ?>)
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>No patients found.</p>
<?php endif; ?>