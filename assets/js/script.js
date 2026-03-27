
// สคริปต์เพื่อให้สีของ SweetAlert ปรับตาม Theme ด้วย
function getSwalBg() {
    return document.documentElement.classList.contains('dark') ? '#140505' : '#ffffff';
}

function getSwalColor() {
    return document.documentElement.classList.contains('dark') ? '#ffffff' : '#1f2937';
}

function openMissionModal(m) {
    document.getElementById('modalTitle').innerText = m.mission_name;
    document.getElementById('modalDesc').innerText = m.mission_detail || 'ไม่มีรายละเอียดเพิ่มเติม';
    document.getElementById('modalTokenReward').innerText = (m.token_reward || 0);

    // ดึงจำนวนเซ็ตมาแสดง
    document.getElementById('modalSetsDisplay').innerText = m.max_sets ? m.max_sets + ' เซ็ต/รอบ' : 'เซ็ตจัดเต็ม';

    const limit = m.daily_limit ? parseInt(m.daily_limit) : 1;
    const done = parseInt(m.today_play_count || 0);
    document.getElementById('modalProgress').innerText = `${done}/${limit}`;

    const media = document.getElementById('modalMedia');
    if (m.final_image && m.final_image !== "assets/images/no-image.png") {
        let path = m.final_image;
        let ext = path.split('.').pop().toLowerCase();
        media.innerHTML = (['mp4', 'webm'].includes(ext)) ?
            `<video src="${path}" autoplay muted loop class="w-full h-full object-cover"></video>` :
            `<img src='${path}' class="w-full h-full object-cover">`;
    } else {
        media.innerHTML = '<div class="w-full h-full flex items-center justify-center bg-gray-200 dark:bg-zinc-900 text-gray-400 dark:text-zinc-600"><i class="fa-solid fa-image fa-3x"></i></div>';
    }

    const isLocked = (done >= limit);
    document.getElementById('modalButtonContainer').innerHTML = isLocked ?
        `<button class="w-full bg-gray-300 dark:bg-zinc-800 text-gray-500 dark:text-zinc-500 py-3 rounded-xl font-bold cursor-not-allowed" disabled>🔒 สิทธิ์เต็มแล้ว</button>` :
        `<button class="w-full bg-primary hover:bg-red-600 text-white py-3 rounded-xl font-bold transition shadow-[0_0_10px_rgba(220,38,38,0.3)]" onclick="location.href='play_mission.php?mission_id=${m.mission_id}'">เริ่มภารกิจ</button>`;

    document.getElementById('missionModal').classList.remove('hidden');
    document.getElementById('missionModal').classList.add('flex');
}

function closeMissionModal() {
    document.getElementById('missionModal').classList.add('hidden');
    document.getElementById('missionModal').classList.remove('flex');
}

function openShop() {
    closeInventory();
    document.getElementById('shopModal').classList.remove('hidden');
    document.getElementById('shopModal').classList.add('flex');
}

function closeShop() {
    document.getElementById('shopModal').classList.add('hidden');
    document.getElementById('shopModal').classList.remove('flex');
}

function openInventory() {
    closeShop();
    document.getElementById('inventoryModal').classList.remove('hidden');
    document.getElementById('inventoryModal').classList.add('flex');
}

function closeInventory() {
    document.getElementById('inventoryModal').classList.add('hidden');
    document.getElementById('inventoryModal').classList.remove('flex');
}

function confirmBuyTitle(id, name, price, userTokens) {
    if (userTokens < price) {
        Swal.fire({
            title: 'Token ไม่พอ!',
            text: 'ออกกำลังกายสะสมเพิ่มก่อนนะ',
            icon: 'error',
            background: getSwalBg(),
            color: getSwalColor()
        });
        return;
    }
    Swal.fire({
        title: 'ยืนยันการซื้อ?',
        html: `ซื้อฉายา <b class="text-yellow-500">${name}</b><br>ราคา ${price} Tokens ใช่หรือไม่?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        background: getSwalBg(),
        color: getSwalColor()
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `actions/process_buy_title.php?title_id=${id}`;
        }
    });
}

// ฟังก์ชันเปิด/ปิดรายการภารกิจที่เกี่ยวข้องสำหรับเมนูท่าออกกำลังกาย
function toggleMissions(id) {
    const missionDiv = document.getElementById('missions-' + id);
    const icon = document.getElementById('icon-' + id).querySelector('i');

    if (missionDiv.classList.contains('hidden')) {
        missionDiv.classList.remove('hidden');
        icon.classList.add('rotate-180', 'text-primary');
    } else {
        missionDiv.classList.add('hidden');
        icon.classList.remove('rotate-180', 'text-primary');
    }
}

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('buy_status') === 'success') {
    Swal.fire({
        title: 'สำเร็จ!',
        text: 'เพิ่มฉายาในคลังแล้ว',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        background: getSwalBg(),
        color: getSwalColor()
    });
}
if (urlParams.get('equip') === 'success') {
    Swal.fire({
        title: 'สำเร็จ!',
        text: 'ติดตั้งฉายาเรียบร้อย',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        background: getSwalBg(),
        color: getSwalColor()
    });
}
if (urlParams.get('unequip') === 'success') {
    Swal.fire({
        title: 'สำเร็จ!',
        text: 'ถอดฉายาเรียบร้อย',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        background: getSwalBg(),
        color: getSwalColor()
    });
}
if (urlParams.get('status') === 'history_cleared') {
    Swal.fire({
        title: 'ล้างประวัติแล้ว!',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        background: getSwalBg(),
        color: getSwalColor()
    });
}
