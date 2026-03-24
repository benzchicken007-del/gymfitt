<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: ../login.php");
    exit();
}

include "../config/config.php";

// ดึงข้อมูลสมาชิก (ตัด Admin ออกถ้าจำเป็น หรือแสดงทั้งหมด)
$result = $conn->query("SELECT * FROM users ORDER BY user_id DESC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GYMFITT Pro - Manage Members</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #ef4444;
            --primary-glow: rgba(239, 68, 68, 0.4);
            --bg-dark: #09090b;
            --card-dark: #18181b;
            --sidebar-w: 260px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-dark);
            color: #fafafa;
            display: flex;
            min-height: 100vh;
            transition: var(--transition);
        }

        /* บังคับให้ SweetAlert อยู่หน้าสุดเสมอ */
        .swal2-container { z-index: 99999 !important; }

        /* ================= SIDEBAR ================= */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--card-dark);
            border-right: 1px solid rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
        }

        .sidebar-header { padding: 30px; text-align: center; }
        .logo { font-size: 24px; font-weight: 700; color: var(--primary); letter-spacing: 1px; text-shadow: 0 0 15px var(--primary-glow); }

        .nav-links { padding: 20px; list-style: none; flex-grow: 1; }
        .nav-links li { margin-bottom: 10px; }
        .nav-links a {
            display: flex; align-items: center; padding: 12px 15px; color: #a1a1aa;
            text-decoration: none; border-radius: 12px; transition: var(--transition);
        }
        .nav-links a i { margin-right: 12px; font-size: 18px; }
        .nav-links a:hover, .nav-links a.active { background: rgba(239, 68, 68, 0.1); color: var(--primary); }

        /* ================= MAIN CONTENT ================= */
        .main-content {
            margin-left: var(--sidebar-w);
            flex-grow: 1;
            padding: 40px;
            width: calc(100% - var(--sidebar-w));
        }

        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }

        /* ================= TABLE DESIGN ================= */
        .table-container {
            background: var(--card-dark);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.03);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 18px; color: #71717a; font-size: 13px; font-weight: 500; border-bottom: 1px solid rgba(255,255,255,0.05); }
        td { padding: 18px; font-size: 14px; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; }
        tr:hover { background: rgba(255,255,255,0.02); }

        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 35px; height: 35px; background: #27272a; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary); }
        
        .level-badge { background: rgba(56, 189, 248, 0.1); color: #38bdf8; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; }
        .exp-text { font-family: monospace; color: #71717a; }
        
        .token-badge { background: rgba(251, 191, 36, 0.1); color: #fbbf24; padding: 4px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }

        /* ================= ACTION BUTTONS ================= */
        .actions { display: flex; gap: 8px; }
        .action-btn {
            width: 35px; height: 35px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none; border: none; cursor: pointer; transition: var(--transition);
        }
        .view-btn { background: rgba(255,255,255,0.05); color: #fff; }
        .edit-btn { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .delete-btn { background: rgba(239, 68, 68, 0.1); color: var(--primary); }
        
        .action-btn:hover { transform: translateY(-3px); filter: brightness(1.2); }

        /* ================= LIGHT MODE FIXES ================= */
        body.light-mode { background-color: #f4f4f5; color: #18181b; }
        .light-mode .sidebar, .light-mode .table-container { background: white; border-color: #e4e4e7; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .light-mode td { border-bottom-color: #f4f4f5; color: #18181b; }
        .light-mode tr:hover { background: #fafafa; }
        .light-mode .user-avatar { background: #f4f4f5; }
        .light-mode h1 { color: #18181b; }
        .light-mode .nav-links a { color: #52525b; }
        .light-mode .exp-text { color: #52525b; }
        .light-mode .user-info div div { color: #18181b !important; }
        .light-mode .user-info div div:last-child { color: #71717a !important; }
        .light-mode .header-section div p { color: #71717a; }
        .light-mode .sidebar div a { color: #18181b; }

        /* แก้ไข Admin Panel Badge ให้รองรับธีมสว่าง */
        .light-mode .admin-profile-badge { 
            background: white !important; 
            border-color: #e4e4e7 !important; 
            color: #18181b !important; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* SweetAlert Styling */
        .swal2-input-custom { font-family: 'Poppins', sans-serif !important; }
    </style>
</head>
<body class="">

    <nav class="sidebar">
        <div class="sidebar-header"><div class="logo">GYMFITT PRO</div></div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
            <li><a href="members.php" class="active"><i class="fa-solid fa-users"></i> จัดการสมาชิก</a></li>
            <li><a href="#" onclick="toggleTheme()"><i class="fa-solid fa-palette"></i> เปลี่ยนธีม</a></li>
        </ul>
        <div style="padding: 20px; border-top: 1px solid rgba(255,255,255,0.05);">
            <a href="logout.php" style="color: #f87171; text-decoration:none; font-size:14px;"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a>
        </div>
    </nav>

    <main class="main-content">
        <header class="header-section">
            <div>
                <h1 style="font-weight: 600;">จัดการสมาชิก</h1>
                <p>ตรวจสอบและแก้ไขข้อมูลผู้ใช้งานในระบบ</p>
            </div>
            <div style="background: var(--card-dark); padding: 8px 20px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.05); font-size: 14px;" class="admin-profile-badge">
                <i class="fa-solid fa-shield-halved" style="color: var(--primary); margin-right: 8px;"></i> Admin Panel
            </div>
        </header>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ข้อมูลสมาชิก</th>
                        <th>Level / EXP</th>
                        <th>เหรียญ (Tokens)</th>
                        <th>ภารกิจ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()){ ?>
                    <tr>
                        <td style="color: #71717a;">#<?=htmlspecialchars($row['user_id'])?></td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                                <div>
                                    <div style="font-weight: 600;"><?=htmlspecialchars($row['username'])?></div>
                                    <div style="font-size: 11px; color: #71717a;">Member since 2026</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="level-badge">LV. <?=htmlspecialchars($row['level'])?></span>
                            <span class="exp-text" style="margin-left: 8px;"><?=number_format($row['exp'])?> XP</span>
                        </td>
                        <td>
                            <div class="token-badge">
                                <i class="fa-solid fa-coins"></i>
                                <?=number_format($row['tokens'])?>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 5px; color: inherit;">
                                <i class="fa-solid fa-fire" style="color: #f97316; font-size: 12px;"></i>
                                <?=htmlspecialchars($row['mission_count'] ?? 0)?>
                            </div>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="action-btn view-btn" title="ดูข้อมูล" 
                                    onclick="showDetail('<?=htmlspecialchars($row['username'])?>', '<?=htmlspecialchars($row['level'])?>', '<?=htmlspecialchars($row['exp'])?>', '<?=htmlspecialchars($row['mission_count'] ?? 0)?>', '<?=htmlspecialchars($row['tokens'])?>')">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <button class="action-btn edit-btn" title="แก้ไข" 
                                    onclick="openEditModal(<?=$row['user_id']?>, '<?=htmlspecialchars($row['username'])?>', <?=$row['tokens']?>)">
                                    <i class="fa-solid fa-user-pen"></i>
                                </button>
                                <button class="action-btn delete-btn" title="ลบ" 
                                    onclick="confirmDelete(<?=$row['user_id']?>, '<?=htmlspecialchars($row['username'])?>')">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Theme persistence
        (function initTheme() {
            const savedTheme = localStorage.getItem("gymfitt-theme");
            if (savedTheme === "light") document.body.classList.add("light-mode");
        })();

        function toggleTheme(){
            document.body.classList.toggle("light-mode");
            localStorage.setItem("gymfitt-theme", document.body.classList.contains("light-mode") ? "light" : "dark");
        }

        function showDetail(username, level, exp, mission, tokens){
            Swal.fire({
                title: `<span style="color: #ef4444">ข้อมูลของ ${username}</span>`,
                html: `
                    <div style="text-align: left; padding: 10px; font-size: 14px;">
                        <p style="margin-bottom: 10px;"><b>เลเวล:</b> ${level}</p>
                        <p style="margin-bottom: 10px;"><b>ค่าประสบการณ์:</b> ${exp} XP</p>
                        <p style="margin-bottom: 10px; color: #fbbf24;"><b>เหรียญทั้งหมด:</b> ${Number(tokens).toLocaleString()} Tokens</p>
                        <p><b>ภารกิจที่สำเร็จ:</b> ${mission} ครั้ง</p>
                    </div>`,
                background: document.body.classList.contains('light-mode') ? '#fff' : '#18181b',
                color: document.body.classList.contains('light-mode') ? '#18181b' : '#fafafa',
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'รับทราบ'
            });
        }

        function confirmDelete(id, username){
            Swal.fire({
                title: 'ยืนยันการลบสมาชิก?',
                text: `บัญชีของคุณ ${username} จะถูกลบถาวร!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#71717a',
                confirmButtonText: 'ลบข้อมูล',
                cancelButtonText: 'ยกเลิก',
                background: document.body.classList.contains('light-mode') ? '#fff' : '#18181b',
                color: document.body.classList.contains('light-mode') ? '#18181b' : '#fafafa'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = `delete_member.php?id=${id}`;
            });
        }

        async function openEditModal(id, username, tokens){
            const isLight = document.body.classList.contains('light-mode');
            const { value: formValues } = await Swal.fire({
                title: 'แก้ไขข้อมูลสมาชิก',
                html:
                    `<div style="text-align:left; margin-bottom:5px; font-size:14px; color: ${isLight ? '#18181b' : '#fafafa'}; opacity:0.8;">ชื่อผู้ใช้งาน</div>` +
                    `<input id="swal-input1" class="swal2-input" value="${username}" style="margin-top:0;">` +
                    `<div style="text-align:left; margin-top:15px; margin-bottom:5px; font-size:14px; color: ${isLight ? '#18181b' : '#fafafa'}; opacity:0.8;">จำนวน Tokens</div>` +
                    `<input id="swal-input2" type="number" class="swal2-input" value="${tokens}" style="margin-top:0;">`,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                confirmButtonText: 'บันทึกการแก้ไข',
                cancelButtonText: 'ยกเลิก',
                background: isLight ? '#fff' : '#18181b',
                color: isLight ? '#18181b' : '#fafafa',
                preConfirm: () => {
                    const name = document.getElementById('swal-input1').value;
                    const tokenValue = document.getElementById('swal-input2').value;
                    if (!name) {
                        Swal.showValidationMessage('โปรดระบุชื่อผู้ใช้งาน!');
                        return false;
                    }
                    return [name, tokenValue];
                }
            });

            if (formValues) {
                const [newName, newToken] = formValues;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'update_member.php';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden'; idInput.name = 'user_id'; idInput.value = id;
                
                const nameInput = document.createElement('input');
                nameInput.type = 'hidden'; nameInput.name = 'username'; nameInput.value = newName;
                
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden'; tokenInput.name = 'tokens'; tokenInput.value = newToken;

                form.appendChild(idInput);
                form.appendChild(nameInput);
                form.appendChild(tokenInput);

                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>