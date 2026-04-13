import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../auth/data/auth_repository.dart';
import '../../core/firebase/push_messaging_binding.dart';
import '../../session/session_controller.dart';

class AppShell extends StatelessWidget {
  const AppShell({super.key, required this.child});

  final Widget child;

  static const _labels = <String, String>{
    'dashboard': 'لوحة',
    'work_orders': 'أوامر العمل',
    'vehicles': 'المركبات',
    'customers': 'العملاء',
    'invoices': 'الفواتير',
    'inventory': 'المخزون',
    'fleet': 'الأسطول',
  };

  @override
  Widget build(BuildContext context) {
    final session = context.watch<SessionController>();
    final modules = session.enabledModules;

    return Scaffold(
      appBar: AppBar(
        title: const Text('عميل الميدان'),
        actions: [
          IconButton(
            tooltip: 'خروج من هذا الجهاز',
            onPressed: () async {
              final fcm = context.read<PushMessagingBinding>().lastRegisteredToken;
              await context.read<AuthRepository>().logout(fcmToken: fcm);
              if (context.mounted) context.go('/login');
            },
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      drawer: Drawer(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            const DrawerHeader(
              decoration: BoxDecoration(color: Color(0xFF0F766E)),
              child: Align(
                alignment: Alignment.bottomRight,
                child: Text(
                  'الوحدات المتاحة',
                  style: TextStyle(color: Colors.white, fontSize: 18),
                ),
              ),
            ),
            for (final m in modules)
              ListTile(
                title: Text(_labels[m] ?? m),
                selected: GoRouterState.of(context).pathParameters['module'] == m,
                onTap: () {
                  context.go('/app/$m');
                  Navigator.of(context).pop();
                },
              ),
          ],
        ),
      ),
      body: child,
    );
  }
}
