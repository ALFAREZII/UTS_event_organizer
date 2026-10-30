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
                "SELECT id, nama_event, tanggal, lokasi, deskripsi
                 FROM events WHERE id = ?"
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
                sendResponse(false, 'event tidak ditemukan', null, 404);
            }

            sendResponse(true, "berhasil", $data, 200);
        }

        // GET Search
        if (isset($_GET['search'])) {
            $search = trim($_GET['search'] ?? '');
            $term = "%" . $search . "%";

            $stmt = mysqli_prepare($koneksi,
                "SELECT id, nama_event, tanggal, lokasi, deskripsi 
                 FROM events 
                 WHERE nama_event LIKE ? OR lokasi LIKE ?
                 ORDER BY id DESC"
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
            "SELECT id, nama_event, tanggal, lokasi, deskripsi
             FROM events 
             ORDER BY id DESC 
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
            empty(trim($input['nama_event'] ?? '')) ||
            empty(trim($input['tanggal'] ?? '')) ||
            empty(trim($input['lokasi'] ?? ''))
        ) {
            sendResponse(false, 'field nama_event, tanggal, lokasi wajib', null, 400);
        }

        $nama_event = trim($input['nama_event']);
        $tanggal    = trim($input['tanggal']);
        $lokasi     = trim($input['lokasi']);
        $deskripsi  = trim($input['deskripsi'] ?? '');

        // Cek duplikat nama event
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM events WHERE nama_event = ?");
        mysqli_stmt_bind_param($stmt, "s", $nama_event);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) > 0) {
            sendResponse(false, 'nama event sudah ada', null, 409);
        }

        $stmt = mysqli_prepare($koneksi, 
            "INSERT INTO events (nama_event, tanggal, lokasi, deskripsi) VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "ssss", $nama_event, $tanggal, $lokasi, $deskripsi);

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
            empty(trim($input['nama_event'] ?? '')) ||
            empty(trim($input['tanggal'] ?? '')) ||
            empty(trim($input['lokasi'] ?? ''))
        ) {
            sendResponse(false, 'field nama_event, tanggal, lokasi wajib', null, 400);
        }

        $nama_event = trim($input['nama_event']);
        $tanggal    = trim($input['tanggal']);
        $lokasi     = trim($input['lokasi']);
        $deskripsi  = trim($input['deskripsi'] ?? '');

        // Cek event ada
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM events WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'event tidak ditemukan', null, 404);
        }

        $stmt = mysqli_prepare($koneksi, 
            "UPDATE events SET nama_event = ?, tanggal = ?, lokasi = ?, deskripsi = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, "ssssi", $nama_event, $tanggal, $lokasi, $deskripsi, $id);

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

        $stmt = mysqli_prepare($koneksi, "SELECT id FROM events WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'event tidak ditemukan', null, 404);
        }

        $stmt = mysqli_prepare($koneksi, "DELETE FROM events WHERE id = ?");
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