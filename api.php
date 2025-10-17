<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");

// Koneksi ke MySQL
$host = 'localhost';
$dbname = 'login_app';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    exit;
}

// 🔹 Handle GET: Jadwal Pelajaran
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'jadwal_pelajaran') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User tidak valid.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT 
            hari,
            jam_mulai,
            jam_selesai,
            mata_pelajaran,
            guru,
            ruangan
        FROM jadwal 
        WHERE user_id = ?
        ORDER BY FIELD(hari, 'senin','selasa','rabu','kamis','jumat','sabtu'), jam_mulai");
        $stmt->execute([$user_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Kelompokkan berdasarkan hari
        $jadwal = [
            'senin' => [],
            'selasa' => [],
            'rabu' => [],
            'kamis' => [],
            'jumat' => [],
            'sabtu' => []
        ];

        foreach ($data as $row) {
            $jadwal[$row['hari']][] = $row;
        }

        echo json_encode(['success' => true, 'data' => $jadwal]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat jadwal: ' . $e->getMessage()]);
    }
    exit;
}

// 🔹 Handle GET: Detail Izin
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'detail_izin') {
    $izin_id = (int)($_GET['izin_id'] ?? 0);
    $user_id = (int)($_GET['user_id'] ?? 0);

    if (!$izin_id || !$user_id) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT 
            id, tanggal, alasan, keterangan, status, file_path, created_at,
            CASE 
                WHEN alasan = 'sakit' THEN 'Sakit'
                WHEN alasan = 'acara_keluarga' THEN 'Acara Keluarga'
                WHEN alasan = 'keperluan_pribadi' THEN 'Keperluan Pribadi'
                ELSE 'Lainnya'
            END as alasan_label,
            CASE 
                WHEN status = 'menunggu' THEN 'Menunggu'
                WHEN status = 'disetujui' THEN 'Disetujui'
                WHEN status = 'ditolak' THEN 'Ditolak'
            END as status_label
        FROM izin 
        WHERE id = ? AND user_id = ?");
        $stmt->execute([$izin_id, $user_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat detail.']);
    }
    exit;
}

// 🔹 Handle GET: Riwayat Izin (hanya yang tidak disembunyikan)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'riwayat_izin') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User tidak valid.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT 
            id,
            tanggal, 
            alasan, 
            keterangan, 
            status,
            CASE 
                WHEN alasan = 'sakit' THEN 'Sakit'
                WHEN alasan = 'acara_keluarga' THEN 'Acara Keluarga'
                WHEN alasan = 'keperluan_pribadi' THEN 'Keperluan Pribadi'
                ELSE 'Lainnya'
            END as alasan_label,
            CASE 
                WHEN status = 'menunggu' THEN 'Menunggu'
                WHEN status = 'disetujui' THEN 'Disetujui'
                WHEN status = 'ditolak' THEN 'Ditolak'
            END as status_label
        FROM izin 
        WHERE user_id = ? AND (hidden_by_user IS NULL OR hidden_by_user = 0)
        ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat riwayat: ' . $e->getMessage()]);
    }
    exit;
}

// 🔹 Handle GET: Tagihan Aktif
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'tagihan_aktif') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User tidak valid.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT 
            id,
            nama_tagihan,
            deskripsi,
            jumlah,
            status,
            tanggal_jatuh_tempo
        FROM tagihan 
        WHERE user_id = ?
        ORDER BY FIELD(status, 'jatuh', 'belum', 'lunas'), tanggal_jatuh_tempo");
        $stmt->execute([$user_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat tagihan: ' . $e->getMessage()]);
    }
    exit;
}

// 🔹 Handle GET: Riwayat Pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'riwayat_pembayaran') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User tidak valid.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT 
            nama_pembayaran,
            jumlah,
            DATE_FORMAT(tanggal_pembayaran, '%d %b %Y') as tanggal
        FROM pembayaran 
        WHERE user_id = ?
        ORDER BY tanggal_pembayaran DESC
        LIMIT 10");
        $stmt->execute([$user_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat riwayat: ' . $e->getMessage()]);
    }
    exit;
}

// 🔹 Handle GET: Daftar Pengajar
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'daftar_pengajar') {
    try {
        $stmt = $pdo->query("SELECT 
            id, 
            nama, 
            mata_pelajaran, 
            jabatan, 
            email, 
            telepon, 
            ruangan 
        FROM pengajar 
        ORDER BY nama");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat data pengajar.']);
    }
    exit;
}

// 🔹 Handle GET: Daftar Materi
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'daftar_materi') {
    $subject = $_GET['subject'] ?? '';
    
    try {
        $sql = "SELECT 
            id, 
            judul, 
            mata_pelajaran, 
            kelas, 
            DATE_FORMAT(tanggal, '%d %b %Y') as tanggal,
            ukuran_file,
            file_path
        FROM materi";
        $params = [];
        
        if ($subject) {
            $sql .= " WHERE mata_pelajaran = ?";
            $params[] = $subject;
        }
        
        $sql .= " ORDER BY tanggal DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat materi: ' . $e->getMessage()]);
    }
    exit;
}

// 🔹 Handle POST request
$isFormData = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;

if ($isFormData) {
    $action = $_POST['action'] ?? '';

    // 🔸 Ajukan Izin
    if ($action === 'ajukan_izin') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? '';
        $alasan = $_POST['alasan'] ?? '';
        $keterangan = $_POST['keterangan'] ?? '';

        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'User tidak terautentikasi.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT nama FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']);
            exit;
        }
        $nama_siswa = $user['nama'];

        if (!$tanggal || !$alasan) {
            echo json_encode(['success' => false, 'message' => 'Tanggal dan alasan wajib diisi.']);
            exit;
        }

        $file_path = null;
        if (!empty($_FILES['lampiran']['name'])) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_name = uniqid() . '_' . basename($_FILES['lampiran']['name']);
            $file_path = $upload_dir . $file_name;

            $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_types)) {
                echo json_encode(['success' => false, 'message' => 'Format file tidak diizinkan.']);
                exit;
            }

            if (!move_uploaded_file($_FILES['lampiran']['tmp_name'], $file_path)) {
                echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file.']);
                exit;
            }
            $file_path = 'uploads/' . $file_name;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO izin (user_id, nama_siswa, tanggal, alasan, keterangan, file_path) VALUES (?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$user_id, $nama_siswa, $tanggal, $alasan, $keterangan, $file_path]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Pengajuan izin berhasil diajukan!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }

    // 🔸 Sembunyikan Izin (Soft Delete)
    } elseif ($action === 'sembunyikan_izin') {
        $izin_id = (int)($_POST['izin_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);

        if (!$izin_id || !$user_id) {
            echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
            exit;
        }

        try {
            // Pastikan izin milik user ini dan belum disembunyikan
            $stmt = $pdo->prepare("UPDATE izin SET hidden_by_user = 1 WHERE id = ? AND user_id = ? AND (hidden_by_user IS NULL OR hidden_by_user = 0)");
            $result = $stmt->execute([$izin_id, $user_id]);

            if ($result && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Pengajuan berhasil disembunyikan.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Pengajuan tidak ditemukan, sudah disembunyikan, atau bukan milik Anda.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyembunyikan: ' . $e->getMessage()]);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Aksi FormData tidak valid.']);
    }

} else {
    // Handle JSON (login & register)
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if ($action === 'register') {
        $nama = trim($data['nama'] ?? '');
        $sekolah = trim($data['sekolah'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$nama || !$sekolah || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar.']);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (nama, sekolah, email, password) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$nama, $sekolah, $email, $hashedPassword]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Registrasi berhasil! Silakan login.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data.']);
        }

    } elseif ($action === 'login') {
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Email dan password wajib diisi.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, nama, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            echo json_encode(['success' => true, 'message' => 'Login berhasil!', 'user' => ['id' => $user['id'], 'nama' => $user['nama']]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Email atau password salah.']);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Aksi JSON tidak valid.']);
    }
}
?>