<?php
require_once "../config.php";
require_once "../helpers/response.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // GET by ID
        if (isset($_GET['id'])) {
            $id = (int) ($_GET['id'] ?? 0);

            $stmt = mysqli_prepare($koneksi, 
                "SELECT p.id, p.nama, p.email, p.no_hp, t.kategori, e.nama_event 
                 FROM peserta p 
                 LEFT JOIN tiket t ON p.tiket_id = t.id 
                 LEFT JOIN events e ON t.event_id = e.id 
                 WHERE p.id = ?"
            );

            if (!$stmt) {
                error_log(mysqli_error($koneksi));
                sendResponse(false, 'terjadi kesalahan pada server', null, 500);
            }

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);

            $data = mysqli_fetch_assoc($r);
            if (!$data) {
                sendResponse(false, 'peserta tidak ditemukan', null, 404);
            }

            sendResponse(true, "berhasil", $data, 200);
        }

        // GET Search
        if (isset($_GET['search'])) {
            $search = trim($_GET['search'] ?? '');
            $term = "%" . $search . "%";

            $stmt = mysqli_prepare($koneksi,
                "SELECT p.id, p.nama, p.email, p.no_hp, t.kategori, e.nama_event 
                 FROM peserta p 
                 LEFT JOIN tiket t ON p.tiket_id = t.id 
                 LEFT JOIN events e ON t.event_id = e.id 
                 WHERE p.nama LIKE ? OR p.email LIKE ?
                 ORDER BY p.id DESC"
            );

            if (!$stmt) {
                error_log(mysqli_error($koneksi));
                sendResponse(false, 'terjadi kesalahan pada server', null, 500);
            }

            mysqli_stmt_bind_param($stmt, "ss", $term, $term);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);

            $data = [];
            while ($row = mysqli_fetch_assoc($r)) {
                $data[] = $row;
            }

            sendResponse(true, "berhasil", $data, 200);
        }

        // GET All + Pagination
        $page  = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 10);
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 1;
        $offset = ($page - 1) * $limit;

        $stmt = mysqli_prepare($koneksi,
            "SELECT p.id, p.nama, p.email, p.no_hp, t.kategori, e.nama_event 
             FROM peserta p 
             LEFT JOIN tiket t ON p.tiket_id = t.id 
             LEFT JOIN events e ON t.event_id = e.id 
             ORDER BY p.id DESC 
             LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }

        mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);

        $data = [];
        while ($row = mysqli_fetch_assoc($r)) {
            $data[] = $row;
        }

        sendResponse(true, "berhasil", $data, 200);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);

        if (
            !is_array($input) ||
            empty(trim($input['nama'] ?? '')) ||
            empty(trim($input['email'] ?? '')) ||
            !isset($input['tiket_id']) || $input['tiket_id'] === ''
        ) {
            sendResponse(false, 'field nama, email, tiket_id wajib', null, 400);
        }

        $nama     = trim($input['nama']);
        $email    = trim($input['email']);
        $no_hp    = trim($input['no_hp'] ?? '');
        $tiket_id = (int) $input['tiket_id'];

        // Cek tiket ada
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM tiket WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $tiket_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'tiket tidak ditemukan', null, 404);
        }

        // Cek email duplikat
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM peserta WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) > 0) {
            sendResponse(false, 'email sudah terdaftar', null, 409);
        }

        $stmt = mysqli_prepare($koneksi, 
            "INSERT INTO peserta (tiket_id, nama, email, no_hp) VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "isss", $tiket_id, $nama, $email, $no_hp);

        if (!mysqli_stmt_execute($stmt)) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }

        $newId = mysqli_insert_id($koneksi);
        sendResponse(true, 'data tersimpan', ['id' => $newId], 201);
        break;

    case 'PUT':
        if (!isset($_GET['id']) || $_GET['id'] === '') {
            sendResponse(false, 'parameter id wajib', null, 400);
        }
        $id = (int) $_GET['id'];

        $input = json_decode(file_get_contents('php://input'), true);

        if (
            !is_array($input) ||
            empty(trim($input['nama'] ?? '')) ||
            empty(trim($input['email'] ?? '')) ||
            !isset($input['tiket_id']) || $input['tiket_id'] === ''
        ) {
            sendResponse(false, 'field nama, email, tiket_id wajib', null, 400);
        }

        $nama     = trim($input['nama']);
        $email    = trim($input['email']);
        $no_hp    = trim($input['no_hp'] ?? '');
        $tiket_id = (int) $input['tiket_id'];

        // Cek peserta ada
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM peserta WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'peserta tidak ditemukan', null, 404);
        }

        // Cek tiket ada
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM tiket WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $tiket_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'tiket tidak ditemukan', null, 404);
        }

        $stmt = mysqli_prepare($koneksi, 
            "UPDATE peserta SET tiket_id = ?, nama = ?, email = ?, no_hp = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, "isssi", $tiket_id, $nama, $email, $no_hp, $id);

        if (!mysqli_stmt_execute($stmt)) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }

        sendResponse(true, 'data berhasil diubah', ['id' => $id], 200);
        break;

    case 'DELETE':
        if (!isset($_GET['id']) || $_GET['id'] === '') {
            sendResponse(false, 'parameter id wajib', null, 400);
        }
        $id = (int) $_GET['id'];

        $stmt = mysqli_prepare($koneksi, "SELECT id FROM peserta WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'peserta tidak ditemukan', null, 404);
        }

        $stmt = mysqli_prepare($koneksi, "DELETE FROM peserta WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (!mysqli_stmt_execute($stmt)) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }

        sendResponse(true, 'data berhasil dihapus', ['id' => $id], 200);
        break;

    default:
        sendResponse(false, 'method tidak didukung', null, 405);
}