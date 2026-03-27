<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}
include "../config/config.php";
$conn->set_charset("utf8mb4");

$success_msg = false;
$error_msg = false;

// ดึงท่าออกกำลังกายทั้งหมดมาเตรียมไว้สร้าง Dropdown
$ex_query = $conn->query("SELECT * FROM exercises ORDER BY exercise_name ASC");
$exercises_list = $ex_query->fetch_all(MYSQLI_ASSOC);

if (isset($_POST['save_mission'])) {
    $name = $_POST['mission_name'];
    $detail = $_POST['mission_detail'];
    $exp = $_POST['exp_reward'];
    $token = $_POST['token_reward'];
    $limit = $_POST['daily_limit'];

    $mission_image = "";
    $targetDir = "../uploads/missions/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    if (!empty($_FILES["mission_image"]["name"])) {
        $ext = pathinfo($_FILES["mission_image"]["name"], PATHINFO_EXTENSION);
        $mission_image = time() . "_mission_img_" . rand(1000, 9999) . "." . $ext;
        move_uploaded_file($_FILES["mission_image"]["tmp_name"], $targetDir . $mission_image);
    }

    $stmt = $conn->prepare("INSERT INTO missions (mission_name, mission_image, mission_detail, exp_reward, token_reward, daily_limit) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("sssiii", $name, $mission_image, $detail, $exp, $token, $limit);

    if ($stmt->execute()) {
        $mission_id = $stmt->insert_id;

        // บันทึกท่าที่เลือกเข้าภารกิจนี้
        if (isset($_POST['exercise_id']) && is_array($_POST['exercise_id'])) {
            $ex_ids = $_POST['exercise_id'];
            $sets = $_POST['sets'];

            for ($i = 0; $i < count($ex_ids); $i++) {
                $e_id = $ex_ids[$i];
                $set_val = $sets[$i];
                $seq = $i + 1;

                $stmt2 = $conn->prepare("INSERT INTO mission_exercises (mission_id, exercise_id, sequence_order, sets) VALUES (?,?,?,?)");
                $stmt2->bind_param("iiii", $mission_id, $e_id, $seq, $set_val);
                $stmt2->execute();
            }
        }
        $success_msg = true;
    } else {
        $error_msg = "Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="th" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างภารกิจ (จัดเซ็ต) - GYMFITT</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#ef4444',
                        card: '#121212',
                        darker: '#0a0a0a'
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                        orbitron: ['Orbitron', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .swal2-container {
            z-index: 99999 !important;
        }

        .gymfitt-swal-popup {
            border-radius: 24px !important;
            padding: 20px !important;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .exercise-row {
            animation: slideIn 0.3s ease;
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-[#050505] text-gray-900 dark:text-white font-sans min-h-screen">

    <script>
        (function() {
            if (localStorage.getItem('gymfitt-theme') === 'light') document.documentElement.classList.remove('dark');
            else document.documentElement.classList.add('dark');
        })();
    </script>

    <!-- Background Effects -->
    <div class="fixed top-20 right-0 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>
    <div class="fixed bottom-0 left-0 w-96 h-96 bg-red-900/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>

    <div class="max-w-4xl mx-auto px-4 py-12 relative z-10">

        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white drop-shadow-sm">
                <i class="fa-solid fa-star text-primary mr-2"></i> สร้างภารกิจใหม่
            </h1>
            <a href="dashboard.php" class="bg-gray-200 dark:bg-zinc-800 hover:bg-gray-300 dark:hover:bg-zinc-700 text-gray-700 dark:text-white px-5 py-2.5 rounded-xl font-bold transition shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> กลับแดชบอร์ด
            </a>
        </div>

        <div class="bg-white dark:bg-card rounded-3xl p-6 md:p-10 border border-gray-200 dark:border-red-900/30 shadow-xl transition-colors">
            <form method="POST" enctype="multipart/form-data" class="space-y-8">

                <!-- ข้อมูลพื้นฐาน -->
                <div>
                    <h2 class="text-xl font-bold border-b border-gray-100 dark:border-zinc-800 pb-4 mb-6 flex items-center gap-2 text-primary">
                        <i class="fa-solid fa-circle-info"></i> ข้อมูลภารกิจ (Mission Info)
                    </h2>

                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1.5">ชื่อภารกิจ <span class="text-primary">*</span></label>
                            <input type="text" name="mission_name" placeholder="เช่น ฟิตเนสเบื้องต้น 3 ท่า" required class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-primary focus:ring-1 focus:ring-primary outline-none transition">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1.5">รายละเอียดภารกิจ</label>
                            <textarea name="mission_detail" rows="2" placeholder="คำอธิบาย หรือเป้าหมายของภารกิจนี้" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-primary focus:ring-1 focus:ring-primary outline-none transition"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1.5">รูปหน้าปกภารกิจ</label>
                            <input type="file" name="mission_image" accept="image/*" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-white hover:file:bg-red-600 transition">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-primary mb-1.5"><i class="fa-solid fa-star"></i> EXP ที่จะได้รับ</label>
                                <input type="number" name="exp_reward" placeholder="เช่น 50" required class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-primary outline-none transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-yellow-500 mb-1.5"><i class="fa-solid fa-coins"></i> Token ที่จะได้รับ</label>
                                <input type="number" name="token_reward" placeholder="เช่น 10" required class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-yellow-500 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1.5"><i class="fa-solid fa-clock"></i> จำกัดเล่นต่อวัน (ครั้ง)</label>
                                <input type="number" name="daily_limit" value="1" min="1" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-primary outline-none transition">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- การจัดเซ็ต -->
                <div>
                    <h2 class="text-xl font-bold border-b border-gray-100 dark:border-zinc-800 pb-4 mb-6 flex items-center gap-2 text-primary">
                        <i class="fa-solid fa-list-check"></i> เพิ่มท่าออกกำลังกายและจัดเซ็ต
                    </h2>

                    <div id="exercise-list" class="space-y-3">
                        <div class="exercise-row flex flex-col sm:flex-row gap-3 items-center bg-gray-50 dark:bg-darker p-4 rounded-2xl border border-gray-200 dark:border-zinc-800 transition hover:border-gray-300 dark:hover:border-zinc-600">
                            <select name="exercise_id[]" required class="w-full sm:flex-1 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white outline-none">
                                <option value="">-- เลือกท่าออกกำลังกาย --</option>
                                <?php foreach ($exercises_list as $ex): ?>
                                    <option value="<?= $ex['exercise_id'] ?>"><?= htmlspecialchars($ex['exercise_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="flex items-center gap-2 w-full sm:w-auto">
                                <input type="number" name="sets[]" placeholder="จำนวน" required min="1" class="w-24 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-center text-gray-900 dark:text-white outline-none">
                                <span class="text-sm text-gray-500 dark:text-zinc-400">เซ็ต</span>
                            </div>
                            <button type="button" onclick="this.parentElement.remove()" class="w-full sm:w-12 h-12 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-primary rounded-xl flex items-center justify-center transition border border-red-100 dark:border-transparent shrink-0" title="ลบท่านี้">
                                <i class="fa-solid fa-trash"></i> <span class="sm:hidden ml-2 font-bold">ลบ</span>
                            </button>
                        </div>
                    </div>

                    <button type="button" onclick="addExerciseRow()" class="mt-4 w-full bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/40 text-blue-600 dark:text-blue-400 font-bold py-3 rounded-xl border border-blue-200 dark:border-blue-900/50 border-dashed transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-plus"></i> เพิ่มท่าออกกำลังกาย
                    </button>
                </div>

                <div class="pt-6 border-t border-gray-100 dark:border-zinc-800">
                    <button type="submit" name="save_mission" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-4 rounded-xl shadow-lg hover:shadow-xl transition transform active:scale-95 flex items-center justify-center gap-2 text-lg">
                        <i class="fa-solid fa-save"></i> บันทึกภารกิจเข้าระบบ
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Template for dynamic rows -->
    <template id="exercise-row-template">
        <div class="exercise-row flex flex-col sm:flex-row gap-3 items-center bg-gray-50 dark:bg-darker p-4 rounded-2xl border border-gray-200 dark:border-zinc-800 transition hover:border-gray-300 dark:hover:border-zinc-600">
            <select name="exercise_id[]" required class="w-full sm:flex-1 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white outline-none">
                <option value="">-- เลือกท่าออกกำลังกาย --</option>
                <?php foreach ($exercises_list as $ex): ?>
                    <option value="<?= $ex['exercise_id'] ?>"><?= htmlspecialchars($ex['exercise_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <input type="number" name="sets[]" value="1" required min="1" class="w-24 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-center text-gray-900 dark:text-white outline-none">
                <span class="text-sm text-gray-500 dark:text-zinc-400">เซ็ต</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="w-full sm:w-12 h-12 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-primary rounded-xl flex items-center justify-center transition border border-red-100 dark:border-transparent shrink-0" title="ลบท่านี้">
                <i class="fa-solid fa-trash"></i> <span class="sm:hidden ml-2 font-bold">ลบ</span>
            </button>
        </div>
    </template>

    <script>
        function addExerciseRow() {
            const template = document.getElementById('exercise-row-template');
            const clone = template.content.cloneNode(true);
            document.getElementById('exercise-list').appendChild(clone);
        }

        <?php if ($success_msg): ?>
            Swal.fire({
                title: 'สำเร็จ!',
                text: 'สร้างภารกิจพร้อมเรียงท่าเรียบร้อยแล้ว',
                icon: 'success',
                background: document.documentElement.classList.contains('dark') ? '#18181b' : '#fff',
                color: document.documentElement.classList.contains('dark') ? '#fafafa' : '#18181b',
                confirmButtonColor: '#10b981',
                customClass: {
                    popup: 'gymfitt-swal-popup'
                }
            }).then(() => window.location = 'dashboard.php');
        <?php endif; ?>

        <?php if ($error_msg): ?>
            Swal.fire({
                title: 'เกิดข้อผิดพลาด!',
                text: '<?= addslashes($error_msg) ?>',
                icon: 'error',
                background: document.documentElement.classList.contains('dark') ? '#18181b' : '#fff',
                color: document.documentElement.classList.contains('dark') ? '#fafafa' : '#18181b',
                confirmButtonColor: '#ef4444',
                customClass: {
                    popup: 'gymfitt-swal-popup'
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>