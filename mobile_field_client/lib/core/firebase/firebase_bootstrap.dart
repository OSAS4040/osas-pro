import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';

/// Initializes Firebase when `FIREBASE_PROJECT_ID` (and peers) are passed via `--dart-define`.
/// CI/analyze builds without defines skip Firebase safely.
Future<bool> ensureFirebaseInitialized() async {
  if (Firebase.apps.isNotEmpty) {
    return true;
  }

  const projectId = String.fromEnvironment('FIREBASE_PROJECT_ID', defaultValue: '');
  if (projectId.isEmpty) {
    if (kDebugMode) {
      debugPrint(
        'Firebase skipped: set FIREBASE_PROJECT_ID (+ API keys) via --dart-define or use flutterfire configure.',
      );
    }
    return false;
  }

  const apiKey = String.fromEnvironment('FIREBASE_API_KEY', defaultValue: '');
  const appId = String.fromEnvironment('FIREBASE_APP_ID', defaultValue: '');
  const senderId = String.fromEnvironment('FIREBASE_MESSAGING_SENDER_ID', defaultValue: '');
  const storageBucket = String.fromEnvironment('FIREBASE_STORAGE_BUCKET', defaultValue: '');
  const iosBundleId = String.fromEnvironment('FIREBASE_IOS_BUNDLE_ID', defaultValue: '');

  if (apiKey.isEmpty || appId.isEmpty || senderId.isEmpty) {
    if (kDebugMode) {
      debugPrint('Firebase skipped: incomplete dart-define set (apiKey/appId/senderId).');
    }
    return false;
  }

  await Firebase.initializeApp(
    options: FirebaseOptions(
      apiKey: apiKey,
      appId: appId,
      messagingSenderId: senderId,
      projectId: projectId,
      storageBucket: storageBucket.isEmpty ? null : storageBucket,
      iosBundleId: iosBundleId.isEmpty ? null : iosBundleId,
    ),
  );

  return true;
}
