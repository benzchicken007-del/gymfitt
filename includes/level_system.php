<?php

function calculateRequiredExp($level){
    return round(40 * pow(1.15, $level - 1));
}

function addExpAndLevelUp($conn, $user_id, $expGain){

    // ดึงข้อมูลปัจจุบัน
    $stmt = $conn->prepare("SELECT level, exp FROM users WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $currentLevel = (int)$user['level'];
    $currentExp = (int)$user['exp'];

    $currentExp += $expGain;

    $leveledUp = false;

    while(true){

        $requiredExp = calculateRequiredExp($currentLevel);

        if($currentExp >= $requiredExp){
            $currentExp -= $requiredExp;
            $currentLevel++;
            $leveledUp = true;
        } else {
            break;
        }
    }

    // อัปเดตฐานข้อมูล
    $stmt = $conn->prepare("UPDATE users SET level=?, exp=? WHERE user_id=?");
    $stmt->bind_param("iii", $currentLevel, $currentExp, $user_id);
    $stmt->execute();
    $stmt->close();

    return [
        "level" => $currentLevel,
        "exp" => $currentExp,
        "leveledUp" => $leveledUp
    ];
}
