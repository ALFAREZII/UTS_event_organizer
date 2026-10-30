
CREATE DATABASE IF NOT EXISTS event_organizer;
USE event_organizer;


CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_event VARCHAR(150) NOT NULL,
    tanggal DATE NOT NULL,
    lokasi VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    UNIQUE KEY uk_nama_event (nama_event)
);

CREATE TABLE tiket (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    harga INT NOT NULL,
    kuota INT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY uk_event_kategori (event_id, kategori)
);

CREATE TABLE peserta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tiket_id INT NOT NULL,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20),
    FOREIGN KEY (tiket_id) REFERENCES tiket(id) ON DELETE CASCADE,
    UNIQUE KEY uk_email (email)
);