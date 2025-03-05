<?php
session_start();
require 'config.php';
require 'user.php';
require 'favori.php'; // Assurez-vous que ce fichier contient la classe Favorite pour gérer les favoris

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = new User($pdo);
$favorite = new Favorite($pdo);

$userData = $user->getUser();
$favorites = $favorite->getFavorites();

// Gérer la requête AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier si c'est bien du JSON
    $data = json_decode(file_get_contents('php://input'), true);

    // Si la requête n'est pas valide
    if ($data === null) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON received']);
        exit;
    }

    // Ajouter un favori
    if ($data['action'] === 'add_favorite') {
        $bookId = $data['book_id'];
        $title = $data['title'];
        $authors = $data['authors'];
        $image = $data['image'];

        // Ajouter le livre aux favoris dans la base de données
        try {
            $favorite->addFavorite($bookId, $title, $authors, $image);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to add to database: ' . $e->getMessage()]);
        }
        exit;

    } elseif ($data['action'] === 'remove_favorite') {
        $bookId = $data['book_id'];

        // Supprimer le livre des favoris dans la base de données
        try {
            $favorite->removeFavorite($bookId);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to remove from database: ' . $e->getMessage()]);
        }
        exit;
    }

    // Si aucune action n'est définie
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
        <a href="index/index.php">Search Books</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    <h1>Profile</h1>
    <form action="profil.php" method="POST">
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
                <form action="profil.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="remove_favorite">
                    <input type="hidden" name="book_id" value="<?php echo $favorite['book_id']; ?>">
                    <button type="submit">Remove</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', () => {
    const resultsDiv = document.getElementById('results');  // Div où les résultats de recherche sont affichés

    // Fonction de recherche (à conserver)
    function searchBooks(query) {
        const url = `https://www.googleapis.com/books/v1/volumes?q=${query}&maxResults=40`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                resultsDiv.innerHTML = ''; // Clear previous results
                const books = data.items;

                if (books) {
                    books.forEach(book => {
                        const bookElement = document.createElement('div');
                        bookElement.classList.add('book');

                        const bookId = book.id;
                        const title = book.volumeInfo.title;
                        const authors = book.volumeInfo.authors ? book.volumeInfo.authors.join(', ') : 'Unknown';
                        const image = book.volumeInfo.imageLinks ? book.volumeInfo.imageLinks.thumbnail : 'https://via.placeholder.com/128x192';

                        bookElement.innerHTML = `
                            <img src="${image}" alt="${title}">
                            <h3>${title}</h3>
                            <p>${authors}</p>
                            <button class="add-favorite" data-book-id="${bookId}" data-title="${title}" data-authors="${authors}" data-image="${image}">Add to Favorites</button>
                        `;

                        resultsDiv.appendChild(bookElement);
                    });
                }
            })
            .catch(error => console.error('Error fetching books:', error));
    }

    // Fonction d'ajout aux favoris
    function addToFavorites(event) {
        const button = event.target;
        const bookId = button.getAttribute('data-book-id');
        const title = button.getAttribute('data-title');
        const authors = button.getAttribute('data-authors');
        const image = button.getAttribute('data-image');

        console.log('Adding to favorites:', bookId, title, authors, image);

        // Envoi des données au serveur avec fetch
        fetch('profil.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'add_favorite',
                book_id: bookId,
                title: title,
                authors: authors,
                image: image
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(result => {
            console.log(result);
            if (result.success) {
                button.textContent = 'Remove from Favorites';
                button.classList.remove('add-favorite');
                button.classList.add('remove-favorite');
                button.removeEventListener('click', addToFavorites);
                button.addEventListener('click', removeFromFavorites);
            } else {
                alert(result.message || 'Failed to add book to favorites.');
            }
        })
        .catch(error => {
            console.error('Error adding to favorites:', error);
            alert('Failed to add book to favorites.');
        });
    }

    // Fonction de suppression des favoris
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
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(result => {
            console.log(result);
            if (result.success) {
                button.textContent = 'Add to Favorites';
                button.classList.remove('remove-favorite');
                button.classList.add('add-favorite');
                button.removeEventListener('click', removeFromFavorites);
                button.addEventListener('click', addToFavorites);
            } else {
                alert(result.message || 'Failed to remove book from favorites.');
            }
        })
        .catch(error => {
            console.error('Error removing from favorites:', error);
            alert('Failed to remove book from favorites.');
        });
    }

    // Ajouter l'événement aux boutons existants pour ajouter ou supprimer des favoris
    document.querySelectorAll('.add-favorite').forEach(button => {
        button.addEventListener('click', addToFavorites);
    });

    document.querySelectorAll('.remove-favorite').forEach(button => {
        button.addEventListener('click', removeFromFavorites);
    });

    // Fonction pour lancer la recherche quand un utilisateur soumet un terme
    const searchForm = document.getElementById('search-form');
    searchForm.addEventListener('submit', event => {
        event.preventDefault();
        const query = document.getElementById('search-input').value;
        searchBooks(query);
    });
});
    </script>
</body>
</html>