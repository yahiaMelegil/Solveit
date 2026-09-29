# تسليم الفرونت — اعتماد الخبير المهني

**هذا التغيير يتطلب تعديل واجهة الأدمن قبل نشر الباك إند.** رابط API وطريقة Bearer Token والصلاحية `experts.reviewKyc` لم تتغير. الذي تغيّر هو طلب الموافقة `POST /api/admin/kyc/applications/{application}/approve`: إرسال جسم فارغ لم يعد مقبولًا، ويرجع 422. لم أعدل أي ملف Front-End ضمن الحزمة.

## واجهة الخبير

- أبقِ التسجيل والدخول ومساحة KYC المحدودة متاحة قبل الاعتماد.
- النموذج الحالي يقبل `credentials` في `PUT /api/expert/kyc`، وتتضمن الرخصة `{type:"license", name, issuer, issueDate, expiryDate}`. بعد حفظ المسودة استخدم `id` المعاد للرخصة عند رفع مستندها الخاص إلى `POST /api/expert/kyc/documents` مع `documentType=credential` و`credentialId` و`file`.
- اعرض للمجالات المنظمة طلب رفع الترخيص ومستند الإثبات؛ لا تكتب للمستخدم أن النظام تحقّق من السجل تلقائيًا. بإمكانه تقديم الطلب دون الرخصة، لكن الأدمن لن يستطيع الموافقة حتى يطلب استكمالها وتُراجع الوثيقة.
- لا حاجة لتغيير تسجيل الدخول أو شكل `GET /api/expert/kyc` الأساسي. النطاق المنظم ينتهي عند أقرب تاريخ بين صلاحية الرخصة والمراجعة التالية، وقد يتوقف ظهور الملف العام عند انتهائه.

## واجهة الأدمن — إلزامي

1. في تفاصيل الطلب، اسمح باختيار نطاق واحد على الأقل متوافق مع `identityAndScope.domain` و`identityAndScope.jurisdiction`. استعمل `id` الفعلي من `credentials` أو `qualifications` أو `experiences` التابعة **لنفس الطلب**.
2. راجع كل مستند عبر مسار مراجعة الوثائق الحالي قبل الموافقة. للمجالات المنظمة يجب اختيار رخصة `credential.type=license` لها مستند مرتبط جرى مراجعته.
3. سجّل بلد الترخيص في `jurisdictionCountry` برمز من حرفين، وضع الرمز نفسه في `professionalReview.verifiedCountry`، مع الجهة المنظمة ورقم التسجيل ورابط HTTPS للمصدر الذي فحصته والحالة `active` وتاريخ المراجعة التالية خلال سنة. يمكن أن يختلف بلد الترخيص عن بلد إقامة الخبير؛ يجب أن يطابق نص `jurisdiction` الطلب المراجع. للاعتماد في بلد آخر يُعدّل الخبير طلب KYC ويعيد تقديمه أولًا.
4. أرسل هذا الطلب بدل الطلب الفارغ:

```json
{
  "scopes": [{
    "domain": "legal",
    "jurisdiction": "Jordan",
    "jurisdictionCountry": "JO",
    "role": "Legal consultant",
    "serviceTypes": ["written_consultation"],
    "languages": ["ar"],
    "evidence": {"type": "credential", "id": 123},
    "professionalReview": {
      "verifiedCountry": "JO",
      "regulator": "Relevant professional authority",
      "registrationNumber": "EXAMPLE-123",
      "verificationSource": "https://example.invalid/official-register",
      "statusChecked": "active",
      "nextReviewAt": "2027-03-28"
    }
  }]
}
```

5. للمجالات غير المنظمة، يكفي دليل مرتبط بالطلب ومراجَع، مثل `{"type":"experience","id":456}` مع CV أو نموذج عمل تمت مراجعته؛ لا تعرض حقول الترخيص الإلزامية لها. قائمة التصنيف الأولية موجودة في `config/expert_verification.php`. المجال غير المصنف يرجع 422 حتى تُعتمد سياسته.
6. اربط أخطاء 422 بحقولها مثل `scopes` و`scopes.0.jurisdictionCountry` و`scopes.0.evidence` و`scopes.0.professionalReview.verifiedCountry` و`scopes.0.validUntil`. لا تعرض رسالة نجاح عند فشل الطلب. يبقى رد النجاح بنفس `status/message/data`؛ يضاف `jurisdictionCountry` إلى النطاقات المعروضة، وقد يكون `null` للنطاقات القديمة. تضيف تفاصيل طلب الأدمن فقط `data.application.professionalReviews`، ولا تُظهر رقم الترخيص أو رابط المراجعة في الصفحات العامة أو لوحة الخبير.

## ترتيب النشر

ارفع تحديث واجهة الأدمن أولًا إلى Staging، ثم Migration وباك إند هذه الحزمة، ثم اختبر تقديم خبير قانوني برخصة صالحة، وحالة غياب الرخصة، وحالة خبير تقني بدليل خبرة. لا تنشر الباك إند إلى Production بينما واجهة الأدمن ما زالت ترسل موافقة فارغة. سجلات الاعتماد القديمة لا تُحذف؛ راجعها يدويًا قبل السماح بخدمة منظمة مدفوعة.

ملف `docs/api/SPRINT1_EXPERT_ELIGIBILITY_CONTRACT.md` يحتوي العقد الكامل والأخطاء والحدود الحالية.
