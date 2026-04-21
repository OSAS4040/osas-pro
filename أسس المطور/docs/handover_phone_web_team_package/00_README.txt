═══════════════════════════════════════════════════════════════════
  حزمة تسليم فريق النشر — مسار الويب: الجوال + OTP
═══════════════════════════════════════════════════════════════════

اقرأوا أولاً (مهم):
  → WHAT_ZIP_CONTAINS_AR.md

الخلاصة:
  • هذه الحزمة ليست «تطبيقاً جاهزاً» ترفعونه فقط فيعمل بدون باقي المشروع.
  • هي: وثائق + سكربت bash يساعدكم بعد أن يكون كود المشروع على الخادم (git pull).

الرفع الفعلي للتطبيق:
  • من Git / Pipeline على الوسم المعتمد، ثم أوامر composer + migrate + npm build
  • أو حسب معماريتكم: Docker / حزمة deployment/official_release_package في المستودع.

السكربت (بعد وجود المستودع على الخادم):
  bash docs/handover_phone_web_team_package/deploy_after_git_pull.sh

الوثيقة الكاملة:
  TEAM_HANDOVER_FULL_AR.md

ملحقات:
  Web_Login_Register_PhoneOtp_Release.md
  PhoneRegistration_IndividualPolicy.md
