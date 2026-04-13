import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';

import 'auth/data/auth_repository.dart';
import 'core/firebase/push_messaging_binding.dart';
import 'design_system/app_theme.dart';
import 'session/session_controller.dart';

/// Owns long-lived listeners (FCM token refresh) tied to [SessionController].
class RootHost extends StatefulWidget {
  const RootHost({
    super.key,
    required this.session,
    required this.authRepo,
    required this.router,
  });

  final SessionController session;
  final AuthRepository authRepo;
  final GoRouter router;

  @override
  State<RootHost> createState() => _RootHostState();
}

class _RootHostState extends State<RootHost> {
  late final PushMessagingBinding _pushBinding;

  @override
  void initState() {
    super.initState();
    _pushBinding = PushMessagingBinding(widget.session, widget.authRepo)..attach();
  }

  @override
  void dispose() {
    _pushBinding.detach();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Provider<PushMessagingBinding>.value(
      value: _pushBinding,
      child: MobileFieldClientApp(router: widget.router),
    );
  }
}

class MobileFieldClientApp extends StatelessWidget {
  const MobileFieldClientApp({
    super.key,
    required this.router,
  });

  final GoRouter router;

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'عميل الميدان',
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      routerConfig: router,
      builder: (context, child) {
        return Directionality(
          textDirection: TextDirection.rtl,
          child: child ?? const SizedBox.shrink(),
        );
      },
    );
  }
}
