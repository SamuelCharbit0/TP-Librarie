<?php
session_start();
require 'config.php';
require 'user.php';
require 'favori.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = new User($pdo);
$favorite = new Favorite($pdo);

$userData = $user->getUser();
$favorites = $favorite->getFavorites();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON received']);
        exit;
    }

    if ($data['action'] === 'add_favorite') {
        $bookId = $data['book_id'];
        $title = $data['title'];
        $authors = $data['authors'];
        $image = $data['image'];

        try {
            $favorite->addFavorite($bookId, $title, $authors, $image);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to add to database: ' . $e->getMessage()]);
        }
        exit;

    } elseif ($data['action'] === 'remove_favorite') {
        $bookId = $data['book_id'];

        try {
            $favorite->removeFavorite($bookId);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to remove from database: ' . $e->getMessage()]);
        }
        exit;
    } elseif ($data['action'] === 'update_profile') {
        $username = $data['username'];
        $email = $data['email'];
        $avatar = $data['avatar'];

        try {
            $user->updateProfile($username, $email, $avatar);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="profile-link">
        <a href="index.php">Search Books</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    <h1>Profile</h1>
    <form id="profileForm">
        <input type="hidden" name="action" value="update_profile">
        <input type="text" name="username" placeholder="Username" value="<?php echo $userData['username']; ?>" required>
        <input type="email" name="email" placeholder="Email" value="<?php echo $userData['email']; ?>" required>
        <input type="text" name="avatar" placeholder="Avatar URL" value="<?php echo $userData['avatar']; ?>">
        <button type="submit">Update Profile</button>
    </form>
    <h2>Favorites</h2>
    <div id="favorites">
        <?php foreach ($favorites as $favorite): ?>
            <div class="favorite">
                <img src="<?php echo $favorite['image']; ?>" alt="<?php echo $favorite['title']; ?>">
                <h3><?php echo $favorite['title']; ?></h3>
                <p><?php echo $favorite['authors']; ?></p>
                <button class="remove-favorite" data-book-id="<?php echo $favorite['book_id']; ?>">Remove</button>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.remove-favorite').forEach(button => {
                button.addEventListener('click', removeFromFavorites);
            });

            document.getElementById('profileForm').addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });

                fetch('profil.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Profile updated successfully.');
                    } else {
                        alert(result.message || 'Failed to update profile.');
                    }
                })
                .catch(error => {
                    console.error('Error updating profile:', error);
                    alert('Failed to update profile.');
                });
            });

            function removeFromFavorites(event) {
                const button = event.target;
                const bookId = button.getAttribute('data-book-id');

                console.log('Removing from favorites:', bookId);

                fetch('profil.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'remove_favorite',
                        book_id: bookId
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        button.closest('.favorite').remove();
                    } else {
                        alert(result.message || 'Failed to remove book from favorites.');
                    }
                })
                .catch(error => {
                    console.error('Error removing from favorites:', error);
                    alert('Failed to remove book from favorites.');
                });
            }
        });
    </script>
</body>
</html>