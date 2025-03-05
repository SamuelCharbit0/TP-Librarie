<?php
class Favorite {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function addFavorite($bookId, $title, $authors, $image) {
        $stmt = $this->pdo->prepare("INSERT INTO favorites (user_id, book_id, title, authors, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $bookId, $title, $authors, $image]);
    }

    public function removeFavorite($bookId) {
        $stmt = $this->pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$_SESSION['user_id'], $bookId]);
    }

    public function getFavorites() {
        $stmt = $this->pdo->prepare("SELECT * FROM favorites WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll();
    }
}
?>