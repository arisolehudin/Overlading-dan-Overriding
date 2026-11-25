<?php
declare(strict_types=1);

// Class dimuat sebelum session_start() agar object di session bisa di-unserialize.
require_once __DIR__ . '/src/bootstrap.php';

session_start();

/* =====================================================================
 * Helper
 * ===================================================================== */

/** Escape output HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format angka menjadi Rupiah, contoh: Rp5.000.000 */
function money(float $amount): string
{
    return 'Rp' . number_format($amount, 0, ',', '.');
}

/** Ubah nilai input menjadi float, atau lempar exception jika bukan angka. */
function toFloat(mixed $value, string $message): float
{
    $number = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($number === false) {
        throw new InvalidArgumentException($message);
    }
    return (float) $number;
}

/** Buat object Student (child class yang sesuai) dari data form. */
function makeStudent(array $data): Student
{
    $id    = trim((string) ($data['id'] ?? ''));
    $name  = trim((string) ($data['name'] ?? ''));
    $major = trim((string) ($data['major'] ?? ''));
    $fee   = toFloat($data['fee'] ?? null, 'Biaya dasar harus berupa angka.');

    return match ((string) ($data['type'] ?? '')) {
        'regular'       => new RegularStudent($id, $name, $major, $fee),
        'scholarship'   => new ScholarshipStudent(
            $id, $name, $major, $fee,
            toFloat($data['discount'] ?? null, 'Diskon harus berupa angka.')
        ),
        'international' => new InternationalStudent($id, $name, $major, $fee),
        default         => throw new InvalidArgumentException('Jenis mahasiswa tidak dikenal.'),
    };
}

/* =====================================================================
 * Inisialisasi repository dan token CSRF
 * ===================================================================== */

$repo = $_SESSION['repository'] ?? null;
if (!$repo instanceof StudentRepository) {
    $repo = new StudentRepository();
    $repo->create(new RegularStudent('M001', 'Alya', 'Informatika', 5000000));
    $repo->create(new ScholarshipStudent('M002', 'Bima', 'Sistem Informasi', 5000000, 40));
    $_SESSION['repository'] = $repo;
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(24));

/* =====================================================================
 * Penanganan aksi form (POST)
 * ===================================================================== */

$error  = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Token keamanan tidak valid.');
        }

        switch ((string) ($_POST['action'] ?? '')) {
            case 'create':
                $repo->create(makeStudent($_POST));
                $notice = 'Mahasiswa berhasil ditambahkan.';
                break;
            case 'update':
                $repo->update((string) ($_POST['old_id'] ?? ''), makeStudent($_POST));
                $notice = 'Mahasiswa berhasil diperbarui.';
                break;
            case 'delete':
                $repo->delete((string) ($_POST['id'] ?? ''));
                $notice = 'Mahasiswa berhasil dihapus.';
                break;
            default:
                throw new InvalidArgumentException('Aksi tidak valid.');
        }

        $_SESSION['repository'] = $repo;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

/* =====================================================================
 * Data untuk tampilan
 * ===================================================================== */

$edit   = isset($_GET['edit']) ? $repo->find((string) $_GET['edit']) : null;
$search = trim((string) ($_GET['q'] ?? ''));

$students = array_values(array_filter(
    $repo->all(),
    fn (Student $s): bool => $search === ''
        || stripos($s->getId() . ' ' . $s->getName() . ' ' . $s->getMajor(), $search) !== false
));

$currentType = $edit?->getType() ?? 'regular';

// Demo overloading lewat __call(): 3 argumen valid, 1 argumen ditolak.
$presenter = new StudentPresenter();
$demoValid = $presenter->showData('M001', 'Alya', 'Informatika');
try {
    $presenter->showData('Alya');
    $demoInvalid = '';
} catch (InvalidArgumentException $ex) {
    $demoInvalid = $ex->getMessage();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRUD Mahasiswa | Praktikum PBO 8</title>
    <style>
        :root {
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            color: #142238;
            background: #f3f6fb;
        }

        * { box-sizing: border-box; }
        body { margin: 0; }

        /* Layout */
        .wrap { max-width: 1100px; margin: auto; padding: 28px 18px; }
        .grid { display: grid; grid-template-columns: 360px 1fr; gap: 20px; margin-top: 22px; }

        header { background: #11243a; color: #fff; padding: 26px 0; }
        header h1 { margin: 4px 0; font-size: 28px; }
        header p { color: #cbd5e1; margin: 0; }
        .eyebrow { color: #9cc9ff; }

        /* Card */
        .card {
            background: #fff;
            border: 1px solid #dfe6f0;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 14px #1d2a3a0b;
        }
        .card.spaced { margin-top: 20px; }
        h2 { margin: 0 0 14px; font-size: 19px; }

        /* Form */
        label { display: block; font-weight: 600; font-size: 13px; margin: 12px 0 5px; }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #b9c5d5;
            border-radius: 8px;
            font: inherit;
        }
        .search-form { display: flex; gap: 8px; margin-bottom: 12px; }

        /* Buttons */
        button, .btn {
            background: #1269d3;
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 650;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn.secondary { background: #eaf0f8; color: #163252; }
        .btn.danger { background: #b42335; }
        .actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }

        /* Table */
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        td, th { text-align: left; padding: 12px 8px; border-bottom: 1px solid #e5eaf1; vertical-align: top; }
        th { color: #51627a; font-size: 12px; }

        /* Misc */
        .tag { font-size: 12px; background: #e7f5ef; color: #167a50; padding: 4px 7px; border-radius: 5px; }
        .alert { padding: 12px 15px; border-radius: 9px; margin: 12px 0; }
        .ok { background: #e7f5ef; }
        .err { background: #feecef; }
        .small { font-size: 13px; color: #52637a; }
        .snippet { background: #f3f6fb; border-radius: 9px; padding: 12px; margin-top: 12px; overflow-wrap: anywhere; }

        @media (max-width: 850px) {
            .grid { grid-template-columns: 1fr; }
            .card { overflow-x: auto; }
            table { min-width: 630px; }
        }
    </style>
</head>
<body>
<header>
    <div class="wrap">
        <div class="small eyebrow">PBO • PERTEMUAN 8 • PHP 8+</div>
        <h1>CRUD Data Mahasiswa</h1>
        <p>Overloading melalui __call() • Overriding melalui inheritance</p>
    </div>
</header>

<main class="wrap">
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>

    <div class="grid">
        <!-- Form tambah / edit -->
        <section class="card">
            <h2><?= $edit ? 'Edit Mahasiswa' : 'Tambah Mahasiswa' ?></h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
                <input type="hidden" name="old_id" value="<?= e($edit?->getId() ?? '') ?>">

                <label for="id">NIM</label>
                <input id="id" name="id" required maxlength="30" value="<?= e($edit?->getId() ?? '') ?>">

                <label for="name">Nama</label>
                <input id="name" name="name" required maxlength="100" value="<?= e($edit?->getName() ?? '') ?>">

                <label for="major">Program Studi</label>
                <input id="major" name="major" required maxlength="100" value="<?= e($edit?->getMajor() ?? '') ?>">

                <label for="fee">Biaya Dasar (Rp)</label>
                <input id="fee" name="fee" type="number" min="0" step="1" required
                       value="<?= e($edit?->getBaseFee() ?? 5000000) ?>">

                <label for="type">Jenis Mahasiswa</label>
                <select id="type" name="type">
                    <option value="regular" <?= $currentType === 'regular' ? 'selected' : '' ?>>Reguler</option>
                    <option value="scholarship" <?= $currentType === 'scholarship' ? 'selected' : '' ?>>Beasiswa</option>
                    <option value="international" <?= $currentType === 'international' ? 'selected' : '' ?>>Internasional</option>
                </select>

                <label for="discount">Diskon Beasiswa (%)</label>
                <input id="discount" name="discount" type="number" min="0" max="100" step="0.1"
                       value="<?= e($edit?->getDiscount() ?? 0) ?>">
                <p class="small">Diskon hanya digunakan untuk mahasiswa beasiswa.</p>

                <div class="actions">
                    <button type="submit"><?= $edit ? 'Simpan Perubahan' : 'Tambah Data' ?></button>
                    <?php if ($edit): ?><a class="btn secondary" href="index.php">Batal</a><?php endif; ?>
                </div>
            </form>
        </section>

        <!-- Daftar mahasiswa -->
        <section class="card">
            <h2>Daftar Mahasiswa (<?= count($students) ?>)</h2>

            <form method="get" class="search-form">
                <input name="q" placeholder="Cari NIM, nama, prodi" value="<?= e($search) ?>">
                <button>Cari</button>
                <a href="index.php" class="btn secondary">Reset</a>
            </form>

            <table>
                <thead>
                    <tr><th>Mahasiswa</th><th>Jenis</th><th>Biaya Akhir</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td>
                            <strong><?= e($student->summary()) ?></strong>
                            <div class="small"><?= e($student->getMajor()) ?></div>
                        </td>
                        <td>
                            <span class="tag"><?= e($student->getType()) ?></span>
                            <?php if ($student->getDiscount() > 0): ?>
                                <div class="small">Diskon <?= e($student->getDiscount()) ?>%</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e(money($student->calculateFee())) ?></strong>
                            <div class="small">Dasar <?= e(money($student->getBaseFee())) ?></div>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="btn secondary" href="?edit=<?= urlencode($student->getId()) ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Hapus mahasiswa ini?')">
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e($student->getId()) ?>">
                                    <button class="btn danger">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$students): ?>
                    <tr><td colspan="4">Data tidak ditemukan.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>

    <!-- Demo konsep OOP -->
    <section class="card spaced">
        <h2>Laboratorium Konsep OOP</h2>
        <p>
            <strong>Overriding:</strong> <code>calculateFee()</code> dan <code>summary()</code>
            dijalankan sesuai class object sebenarnya.
            <strong>Overloading PHP:</strong> <code>showData()</code> tidak dideklarasikan;
            panggilannya ditangani <code>StudentPresenter::__call()</code> dan hanya menerima
            tepat tiga string (id, name, major).
        </p>
        <div class="snippet">
            <div><?= e($demoValid) ?></div>
            <div class="small">showData('Alya') → <?= e($demoInvalid) ?></div>
        </div>
        <p class="small">
            Penyimpanan menggunakan PHP Session untuk tujuan praktikum. Tidak memakai database;
            data dapat hilang saat session berakhir.
        </p>
    </section>
</main>
</body>
</html>