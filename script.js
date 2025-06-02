document.addEventListener('DOMContentLoaded', function() {
    const deviceIdInput = document.getElementById('deviceId');
    const generateBtn = document.getElementById('generateBtn');
    const resultContainer = document.getElementById('resultContainer');
    const activationCode = document.getElementById('activationCode');
    const copyBtn = document.getElementById('copyBtn');
    const expirySelect = document.getElementById('expiryTime'); // اختيار مدة الصلاحية
    
    // عنوان السيرفر (قم بتغييره للخادم الفعلي)
    const SERVER_URL = 'server/index.php';

    // تحقق من صحة الإدخال
    function isValidDeviceId(id) {
        return id && id.length > 8;
    }

    // توليد كود تفعيل مشفر فريد للجهاز - خوارزمية جديدة متطابقة مع التطبيق
    function generateActivationCode(deviceId, expiryDays) {
        // الخوارزمية الجديدة المستخدمة في التطبيق
        const prefix = 'MWD';
        
        // حساب وقت انتهاء الصلاحية (عدد الثواني منذ 1970/1/1)
        const currentTime = Math.floor(Date.now() / 1000);
        const expiryTime = currentTime + (expiryDays * 24 * 60 * 60); // تحويل الأيام إلى ثواني
        
        // تحويل وقت الانتهاء إلى سلسلة من 8 أحرف (base36)
        const expiryHex = expiryTime.toString(36).padStart(8, '0').substring(0, 8);
        
        // استخراج أول 8 أحرف من معرف الجهاز
        let deviceIdPrefix = deviceId.substring(0, 8);
        
        // تحويل كل حرف بإضافة 2 لقيمته
        let transformedChars = '';
        for (let i = 0; i < deviceIdPrefix.length; i++) {
            let charCode = deviceIdPrefix.charCodeAt(i);
            charCode = charCode + 2;  // إضافة 2 لقيمة الحرف
            transformedChars += String.fromCharCode(charCode);
        }
        
        // دمج المعرف المحول مع وقت الانتهاء
        // نأخذ 4 أحرف من المعرف المحول و 8 أحرف من وقت الانتهاء
        let code = transformedChars.substring(0, 4) + expiryHex;
        
        // أخذ أول 12 حرف وإضافة البادئة
        return prefix + code.substring(0, 12);
    }

    // تنسيق تاريخ ووقت الانتهاء بشكل مقروء
    function formatExpiryDate(expiryDays) {
        const expiryDate = new Date();
        expiryDate.setDate(expiryDate.getDate() + expiryDays);
        
        const day = expiryDate.getDate().toString().padStart(2, '0');
        const month = (expiryDate.getMonth() + 1).toString().padStart(2, '0');
        const year = expiryDate.getFullYear();
        const hours = expiryDate.getHours().toString().padStart(2, '0');
        const minutes = expiryDate.getMinutes().toString().padStart(2, '0');
        
        return `${day}/${month}/${year} ${hours}:${minutes}`;
    }
    
    // إرسال الكود إلى الخادم للتخزين
    function sendCodeToServer(code, deviceId, expiryDays) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('code', code);
            formData.append('device_id', deviceId);
            formData.append('expiry', expiryDays);
            
            fetch(`${SERVER_URL}?action=add_code`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    resolve(data);
                } else {
                    reject(data.message || 'حدث خطأ في حفظ الكود');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                reject('حدث خطأ في الاتصال بالخادم');
            });
        });
    }

    // معالجة زر التوليد
    generateBtn.addEventListener('click', function() {
        const deviceId = deviceIdInput.value.trim();
        const expiryDays = parseInt(expirySelect.value);
        
        if (!isValidDeviceId(deviceId)) {
            alert('الرجاء إدخال معرف جهاز صالح (أكثر من 8 أحرف)');
            return;
        }
        
        const code = generateActivationCode(deviceId, expiryDays);
        const expiryDateString = formatExpiryDate(expiryDays);
        
        // إظهار حالة التحميل
        generateBtn.textContent = 'جاري الحفظ...';
        generateBtn.disabled = true;
        
        // إرسال الكود إلى الخادم
        sendCodeToServer(code, deviceId, expiryDays)
            .then(() => {
                // عرض النتيجة
                activationCode.textContent = code;
                document.getElementById('expiryInfo').textContent = `ينتهي في: ${expiryDateString}`;
                resultContainer.classList.remove('hidden');
                
                // حفظ الكود المولد مع معرف الجهاز في التخزين المحلي
                const savedCodes = JSON.parse(localStorage.getItem('activationCodes') || '{}');
                savedCodes[deviceId] = {
                    code: code,
                    expiry: expiryDays,
                    expiryDate: expiryDateString
                };
                localStorage.setItem('activationCodes', JSON.stringify(savedCodes));
            })
            .catch(error => {
                alert(`فشل في حفظ الكود: ${error}`);
            })
            .finally(() => {
                // إعادة زر التوليد إلى حالته الطبيعية
                generateBtn.textContent = 'إنشاء كود التفعيل';
                generateBtn.disabled = false;
            });
    });

    // نسخ الكود إلى الحافظة
    copyBtn.addEventListener('click', function() {
        const codeText = activationCode.textContent;
        
        navigator.clipboard.writeText(codeText)
            .then(() => {
                // تغيير نص الزر مؤقتًا للإشارة إلى النجاح
                copyBtn.textContent = 'تم النسخ!';
                setTimeout(() => {
                    copyBtn.textContent = 'نسخ';
                }, 2000);
            })
            .catch(err => {
                console.error('فشل في نسخ النص: ', err);
                
                // طريقة بديلة للنسخ
                const textarea = document.createElement('textarea');
                textarea.value = codeText;
                textarea.style.position = 'fixed';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                
                try {
                    document.execCommand('copy');
                    copyBtn.textContent = 'تم النسخ!';
                    setTimeout(() => {
                        copyBtn.textContent = 'نسخ';
                    }, 2000);
                } catch (e) {
                    alert('حدث خطأ أثناء نسخ الكود. الرجاء نسخه يدويًا.');
                }
                
                document.body.removeChild(textarea);
            });
    });
}); 