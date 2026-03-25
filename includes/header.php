<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Gymfitt'; ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class', // เปิดระบบสลับธีม
            theme: {
                extend: {
                    colors: {
                        // 🌙 กลุ่มสีสำหรับ "ธีมมืด" (เปลี่ยนจากดำสนิท เป็น แดงเข้ม)
                        darker: '#1a0000', // พื้นหลังหลัก (แดงมืดมาก)
                        dark: '#380000', // แถบเมนูด้านบน (แดงมืด)
                        card: '#450a0a', // กล่องข้อความต่างๆ (แดงเข้ม)

                        // ☀️ กลุ่มสีสำหรับ "ธีมสว่าง" (เปลี่ยนจากขาว/เทา เป็น แดงอ่อนๆ)
                        white: '#fff1f2', // เปลี่ยนสีขาว เป็น แดงอมชมพูสว่าง (Rose 50)
                        gray: {
                            50: '#fef2f2', // เปลี่ยนพื้นหลังหลัก เป็น แดงอ่อนสุด (Red 50)
                            100: '#fee2e2', // แดงอ่อนลงมาอีกนิด (Red 100)
                            200: '#fecaca',
                            800: '#7f1d1d', // สีตัวหนังสือรอง (เปลี่ยนจากเทา เป็น แดงเข้ม)
                            900: '#450a0a', // สีตัวหนังสือหลัก (เปลี่ยนจากดำ เป็น แดงมืดสนิท เพื่อให้อ่านง่าย)
                        },
                        primary: '#dc2626', // สีปุ่มหลัก (แดงสด)
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Kanit', sans-serif;
        }

        .glow-title {
            text-shadow: 0 0 12px currentColor;
        }

        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-darker text-gray-900 dark:text-white min-h-screen transition-colors duration-500">