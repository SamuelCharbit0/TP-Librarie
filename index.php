<?php 

// 1 - Pouvoir chercher des livres (genre ou nom) via l'API google Books soit avec JS (fetch ou axios) soit cURL
// 2 - UN système de login et signup pour les Users (si pas login, il peut pas chercher)
// 3 - Une fois connecté le user peut ajouter des livres en favoris qui s'enregistrent en BDD. Il doit pouvoir aussi 
// supprimer ceux qu'il veut effacer => Pour tout ce qui est BDD avec PHP on utilise PDO et de requetes préparées
// 4 - Le user a un espace de profil dont il peut modifier les informations (ex : nom, email, avatar)

// Notions à utiliser en PHP : 
// - les superglobales ($_POST, $_GET, $_SESSION)
// - PDO pour se connecter à la BDD 
// - Faire des requetes SQL (et préparées si besoin)
// - cURL pour faire des requetes API (ou sinon fetch avec JS)

// Notions en JS :  
// - fetch ou axios pour le call API si pas en PHP

// En BDD (phpMyAdmin) : 
// - Créer les tables nécessaires (au moins User et Livres)

// Pour l'API :     
// https://developers.google.com/books

// Idée globale : 
// On part du front (ou on fera nos requetes API)
// Lorsque un User enregistre un livre on doit transmettre ce changement à la BDD 
// PHP / JS ----> utilisation de PDO -----> envoi en BDD

?>

<?php
session_start();
require 'config.php';
require 'user.php';
require 'favori.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$favorite = new Favorite($pdo);
$results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = $_POST['query'];
    $genre = $_POST['genre'];
    $results = searchBooks($query, $genre);
}

function searchBooks($query, $genre) {
    $apiKey = 'clé API';
    $url = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($query);
    if ($genre) {
        $url .= "+subject:" . urlencode($genre);
    }
    $url .= "&key=$apiKey&maxResults=40";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resp = curl_exec($ch);
    if (curl_error($ch)) {
        var_dump(curl_error($ch));
    }
    curl_close($ch);

    return json_decode($resp, true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book App</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="profile-link">
        <a href="profil.php">Profile</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    <h1>Book Search</h1>
    <form id="searchForm" method="POST" action="index.php">
        <input type="text" id="query" name="query" placeholder="Search for books..." required>
        <select id="genre" name="genre">
            <option value="">All Genres</option>
            <option value="Fiction">Fiction</option>
            <option value="Fantasy">Fantasy</option>
            <option value="Science Fiction">Science Fiction</option>
            <option value="Mystery">Mystery</option>
            <option value="Romance">Romance</option>
            <option value="Horror">Horror</option>
            <option value="Thriller">Thriller</option>
            <option value="Biography">Biography</option>
            <option value="History">History</option>
            <option value="Self-Help">Self-Help</option>
        </select>
        <button type="submit">Search</button>
    </form>
    <div id="results" class="results-grid">
        <?php if (!empty($results['items'])): ?>
            <?php foreach ($results['items'] as $book): ?>
                <div class="book">
                    <img src="<?php echo $book['volumeInfo']['imageLinks']['thumbnail'] ?? 'default-image-url.jpg'; ?>" alt="<?php echo $book['volumeInfo']['title']; ?>">
                    <h3><?php echo $book['volumeInfo']['title']; ?></h3>
                    <p><?php echo implode(', ', $book['volumeInfo']['authors']); ?></p>
                    <?php if ($favorite->isFavorite($book['id'])): ?>
                        <button class="remove-favorite" data-book-id="<?php echo $book['id']; ?>">Remove from Favorites</button>
                    <?php else: ?>
                        <button class="add-favorite" data-book-id="<?php echo $book['id']; ?>" data-title="<?php echo $book['volumeInfo']['title']; ?>" data-authors="<?php echo implode(', ', $book['volumeInfo']['authors']); ?>" data-image="<?php echo $book['volumeInfo']['imageLinks']['thumbnail'] ?? 'default-image-url.jpg'; ?>">Add to Favorites</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Aucun résultats trouvé.</p>
        <?php endif; ?>
    </div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const apiKey = 'AIzaSyB2P3XV5HIiHfW2V6qfniPsf10b-K_6Ihc';
    const searchForm = document.getElementById('searchForm');
    const resultsDiv = document.getElementById('results');

    searchForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const query = document.getElementById('query').value;
        const genre = document.getElementById('genre').value;
        const url = `https://www.googleapis.com/books/v1/volumes?q=${query}${genre ? `+categories:${genre}` : ''}&key=${apiKey}&maxResults=40`;
        const response = await fetch(url);
        const data = await response.json();
        displayResults(data.items);
    });

    function displayResults(books) {
        resultsDiv.innerHTML = '';
        books.forEach(book => {
            const bookDiv = document.createElement('div');
            bookDiv.classList.add('book');
            bookDiv.innerHTML = `
                <img src="${book.volumeInfo.imageLinks?.thumbnail || 'default-image-url.jpg'}" alt="${book.volumeInfo.title}">
                <h3>${book.volumeInfo.title}</h3>
                <p>${book.volumeInfo.authors ? book.volumeInfo.authors.join(', ') : 'Unknown Author'}</p>
                <button class="add-favorite" data-book-id="${book.id}" data-title="${book.volumeInfo.title}" data-authors="${book.volumeInfo.authors ? book.volumeInfo.authors.join(', ') : 'Unknown Author'}" data-image="${book.volumeInfo.imageLinks?.thumbnail || 'default-image-url.jpg'}">Add to Favorites</button>
            `;
            resultsDiv.appendChild(bookDiv);
        });

        document.querySelectorAll('.add-favorite').forEach(button => {
            button.addEventListener('click', addToFavorites);
        });

        document.querySelectorAll('.remove-favorite').forEach(button => {
            button.addEventListener('click', removeFromFavorites);
        });
    }

    function addToFavorites(event) {
        const button = event.target;
        const bookId = button.getAttribute('data-book-id');
        const title = button.getAttribute('data-title');
        const authors = button.getAttribute('data-authors');
        const image = button.getAttribute('data-image');

        console.log('Adding to favorites:', bookId, title, authors, image);

        fetch('profil.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action: 'add_favorite', book_id: bookId, title: title, authors: authors, image: image })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                button.textContent = 'Remove from Favorites';
                button.classList.remove('add-favorite');
                button.classList.add('remove-favorite');
                button.removeEventListener('click', addToFavorites);
                button.addEventListener('click', removeFromFavorites);
            } else {
                alert('Failed to add book to favorites.');
            }
        })
        .catch(error => {
            console.error('Error adding to favorites:', error);
            alert('Failed to add book to favorites.');
        });
    }

    function removeFromFavorites(event) {
        const button = event.target;
        const bookId = button.getAttribute('data-book-id');

        console.log('Removing from favorites:', bookId);

        fetch('profil.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action: 'remove_favorite', book_id: bookId })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                button.textContent = 'Add to Favorites';
                button.classList.remove('remove-favorite');
                button.classList.add('add-favorite');
                button.removeEventListener('click', removeFromFavorites);
                button.addEventListener('click', addToFavorites);
            } else {
                alert('Failed to remove book from favorites.');
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