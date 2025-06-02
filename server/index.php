<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// تحميل قاعدة البيانات من الملف
function loadDatabase() {
    $dbFile = 'codes_db.json';
    if (file_exists($dbFile)) {
        return json_decode(file_get_contents($dbFile), true);
    }
    return [
        'codes' => [],
        'logs' => []
    ];
}

// حفظ قاعدة البيانات إلى الملف
function saveDatabase($db) {
    file_put_contents('codes_db.json', json_encode($db, JSON_PRETTY_PRINT));
}

// إضافة سجل إلى السجلات
function addLog($db, $deviceId, $code, $action, $ip) {
    $db['logs'][] = [
        'timestamp' => time(),
        'device_id' => $deviceId,
        'code' => $code,
        'action' => $action,
        'ip' => $ip
    ];
    return $db;
}

// تحديد الإجراء المطلوب
$action = isset($_GET['action']) ? $_GET['action'] : '';
$db = loadDatabase();

switch ($action) {
    // إضافة كود جديد
    case 'add_code':
        if (isset($_POST['code']) && isset($_POST['device_id']) && isset($_POST['expiry'])) {
            $code = $_POST['code'];
            $deviceId = $_POST['device_id'];
            $expiry = (int)$_POST['expiry'];
            $expiryTime = time() + ($expiry * 24 * 60 * 60);
            
            $db['codes'][$code] = [
                'device_id' => $deviceId,
                'created' => time(),
                'expiry' => $expiryTime,
                'status' => 'active'
            ];
            
            $db = addLog($db, $deviceId, $code, 'generate', $_SERVER['REMOTE_ADDR']);
            saveDatabase($db);
            
            echo json_encode(['status' => 'success', 'message' => 'تم إضافة الكود بنجاح']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'بيانات غير كاملة']);
        }
        break;
    
    // التحقق من صلاحية كود
    case 'verify':
        if (isset($_POST['code']) && isset($_POST['device_id'])) {
            $code = $_POST['code'];
            $deviceId = $_POST['device_id'];
            $currentTime = time();
            
            if (isset($db['codes'][$code])) {
                $codeData = $db['codes'][$code];
                
                // التحقق من الحالة: هل الكود نشط أم تم إلغاؤه
                if ($codeData['status'] === 'revoked') {
                    $db = addLog($db, $deviceId, $code, 'verify_failed_revoked', $_SERVER['REMOTE_ADDR']);
                    saveDatabase($db);
                    echo json_encode([
                        'status' => 'error', 
                        'code' => 'revoked',
                        'message' => 'تم إلغاء هذا الكود من قبل المسؤول'
                    ]);
                    break;
                }
                
                // التحقق من تاريخ انتهاء الصلاحية
                if ($currentTime > $codeData['expiry']) {
                    $db = addLog($db, $deviceId, $code, 'verify_failed_expired', $_SERVER['REMOTE_ADDR']);
                    saveDatabase($db);
                    echo json_encode([
                        'status' => 'error', 
                        'code' => 'expired',
                        'message' => 'انتهت صلاحية هذا الكود'
                    ]);
                    break;
                }
                
                // تحديث آخر استخدام للكود
                $db['codes'][$code]['last_used'] = $currentTime;
                $db['codes'][$code]['last_device'] = $deviceId;
                
                $db = addLog($db, $deviceId, $code, 'verify_success', $_SERVER['REMOTE_ADDR']);
                saveDatabase($db);
                
                // حساب الوقت المتبقي
                $remainingTime = $codeData['expiry'] - $currentTime;
                $remainingHours = floor($remainingTime / 3600);
                $remainingMinutes = floor(($remainingTime % 3600) / 60);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'الكود صالح',
                    'remaining_hours' => $remainingHours,
                    'remaining_minutes' => $remainingMinutes
                ]);
            } else {
                $db = addLog($db, $deviceId, $code, 'verify_failed_invalid', $_SERVER['REMOTE_ADDR']);
                saveDatabase($db);
                echo json_encode([
                    'status' => 'error',
                    'code' => 'invalid',
                    'message' => 'الكود غير صالح'
                ]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'بيانات غير كاملة']);
        }
        break;
    
    // إلغاء كود (تعطيله)
    case 'revoke':
        if (isset($_POST['code']) && isset($_POST['admin_key'])) {
            $code = $_POST['code'];
            $adminKey = $_POST['admin_key'];
            
            // التحقق من مفتاح المسؤول (يمكن تغييره لزيادة الأمان)
            if ($adminKey !== 'admin_secret_key_123') {
                echo json_encode(['status' => 'error', 'message' => 'مفتاح المسؤول غير صحيح']);
                break;
            }
            
            if (isset($db['codes'][$code])) {
                $db['codes'][$code]['status'] = 'revoked';
                $deviceId = $db['codes'][$code]['device_id'];
                
                $db = addLog($db, $deviceId, $code, 'revoke', $_SERVER['REMOTE_ADDR']);
                saveDatabase($db);
                
                echo json_encode(['status' => 'success', 'message' => 'تم إلغاء الكود بنجاح']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'الكود غير موجود']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'بيانات غير كاملة']);
        }
        break;
    
    // الحصول على قائمة الأكواد
    case 'list_codes':
        if (isset($_POST['admin_key'])) {
            $adminKey = $_POST['admin_key'];
            
            // التحقق من مفتاح المسؤول
            if ($adminKey !== 'admin_secret_key_123') {
                echo json_encode(['status' => 'error', 'message' => 'مفتاح المسؤول غير صحيح']);
                break;
            }
            
            echo json_encode([
                'status' => 'success',
                'codes' => $db['codes']
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'بيانات غير كاملة']);
        }
        break;
    
    // الحصول على سجلات النشاط
    case 'get_logs':
        if (isset($_POST['admin_key'])) {
            $adminKey = $_POST['admin_key'];
            
            // التحقق من مفتاح المسؤول
            if ($adminKey !== 'admin_secret_key_123') {
                echo json_encode(['status' => 'error', 'message' => 'مفتاح المسؤول غير صحيح']);
                break;
            }
            
            echo json_encode([
                'status' => 'success',
                'logs' => $db['logs']
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'بيانات غير كاملة']);
        }
        break;
    
    default:
        echo json_encode(['status' => 'error', 'message' => 'إجراء غير معروف']);
}
?> 