> Historical core-privacy release evidence. The2026-09-30 Expert renewal addition supersedes renewal deferral below; latest cumulative results: [renewal verification](../expert-renewal/VERIFICATION.md).

# تقرير تسليم Sprint 1

تم تنفيذ Core Profile وContact/Privacy Preferences وSpecialized Contexts وConsent بإصدارات السياسات، مع الحفاظ على فصل User/Expert/Admin وعقود Sprint 0. أضيف تصدير JSON فعلي مشفر، وتنزيل محمي بتأكيد كلمة المرور، وطلبات حذف موثقة تنتقل إلى deferred دون حذف نهائي.

## التصميم

Controllers خفيفة، Form Requests، API Resources، Policies وGate::authorize للأدمن، Enums للحالات، وخدمات للعمليات متعددة الخطوات. معاملات وقفل صف المالك عند الإنشاء والتكرار، وقفل الطلب أثناء الانتقالات، وexpectedVersion لمنع الكتابة فوق التغييرات، وIdempotency-Key للأوامر المناسبة. المهام تحمل request ID فقط وتستخدم lease/token لمنع اكتمال عامل قديم على حساب عامل أحدث.

## البيانات

أضيفت11 جداول: user_profiles، user_profile_versions، user_preferences، specialized_contexts، specialized_context_versions، policy_versions، user_consent_records، data_rights_requests، data_rights_request_items، audit_events، idempotency_records. الاسم والبريد والمصادقة تبقى داخل users؛ التفاصيل الجديدة في علاقات مستقلة. لا تعديل على جداول Expert/Admin أوKYC. تفاصيل الأعمدة والعلاقات والفهارس في DATABASE_MAPPING.md.

## الحالات والمسارات

Context: active ⇄ archived؛ وكلاهما إلى deleted منطقيًا. الموافقات سجل granted/declined/withdrawn مع تاريخ لا يستبدل. التصدير requested → processing → completed، مع failed وإعادة المحاولة. الحذف requested → processing → deferred؛ إلغاء الطلب متاح للحالات المسموحة. rejected حالة محجوزة دون واجهة قرار في هذا التسليم.

26 endpoint جديدة:23 للمستخدم و3 للأدمن؛ الإجمالي93. قائمة Method/URL/Route/Permission كاملة في ENDPOINTS.md والعقد. الصلاحيات: users.consentMetadata.view وdataRequests.viewAny وdataRequests.view. لا صلاحية عامة لعرض Context أوتصدير مستخدم آخر. PERMISSION_MATRIX.md يوضح الفصل الكامل.

## التحقق الفعلي

- 211 اختبار ناجح و1333 Assertion، تشمل156 اختبار Sprint 0 الأصلي.
- 55 اختبار Sprint 1 مركز و469 Assertion.
- composer validate --strict وPint --test ناجحان.
- Migration up/down/up ناجحة على SQLite مع حفظ المستخدم الموجود وفحص FK.
-46 Fixture JSON مأخوذة من الاستجابات الفعلية للاختبارات؛ سياساتها ومهلتها الزمنية أمثلة اختبار فقط.

## التشغيل والتسليم للواجهة

طبّق الملفات بمساراتها الأصلية، ثم شغّل migrate --force وPrivacyPermissionsSeeder للإضافة الآمنة دون إعادة مزامنة تخصيصات RBAC. راجع DEPLOYMENT.md قبل التشغيل، واضبط due_days من قرار معتمد، وانشر نصوص السياسات الفعلية، وشغّل queue/scheduler والتخزين الخاص. لا تستخدم قيم Fixtures كسياسة قانونية. الحزمة تحتوي CHANGED_FILES.md بكل ملف مضاف أو معدل وSPRINT1_MANIFEST.sha256 للتحقق.

العقد ثابت1.0.0. FRONTEND_HANDOFF.md يوضح البيانات والأخطاء والتكرار وتجربة الحالات. ربط React يبدأ بعد إغلاق واجهات Sprint 0؛ لم يتم اختباره هنا. لا توجد نتيجة Staging أوEnd-to-End مزعومة.

## المؤجل

الحذف النهائي/anonymization والاحتفاظ القانوني والمالي والنسخ الاحتياطية، تجديد/استئناف اعتماد الخبير، قرارات الأدمن على طلبات البيانات، Case Core، تشغيل AI/Recording، وتحقق بيئة الإنتاج والتكامل مع React. DEFERRED.md يحدد السلوك الحالي وما يلزم لإغلاق كل بند.

## متابعة التشغيل — النسخة الثانية

أضيف اختبار بتشغيل Queue حقيقية وعامل PHP مستقل، مع Cache من قاعدة البيانات وتخزين خاص فعلي. نجح التصدير المشفر والتنزيل المحمي وإعادة الإرسال وعزل الحسابات وانتهاء الملف وتنظيفه، وكذلك انتقال الحذف إلى deferred. لم يتطلب الفحص تعديل منطق التطبيق أوالعقد. التفاصيل في QUEUE_INTEGRATION.md.
