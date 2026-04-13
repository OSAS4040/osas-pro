import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../auth/presentation/login_page.dart';
import '../features/shell/app_shell.dart';
import '../features/shell/module_home_page.dart';
import '../session/session_controller.dart';

GoRouter createAppRouter(SessionController session) {
  return GoRouter(
    initialLocation: session.isAuthenticated ? '/app/${session.homeScreen}' : '/login',
    refreshListenable: session,
    redirect: (BuildContext context, GoRouterState state) {
      final loc = state.uri.path;
      final authed = session.isAuthenticated;

      if (!authed) {
        if (loc == '/login') return null;
        return '/login';
      }

      if (loc == '/login') {
        return '/app/${session.homeScreen}';
      }

      if (loc.startsWith('/app/')) {
        final m = state.pathParameters['module'];
        if (m != null && !session.canOpenModule(m)) {
          return '/app/${session.homeScreen}';
        }
      }

      return null;
    },
    routes: [
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginPage(),
      ),
      ShellRoute(
        builder: (context, state, child) => AppShell(child: child),
        routes: [
          GoRoute(
            path: '/app/:module',
            builder: (context, state) {
              final id = state.pathParameters['module'] ?? session.homeScreen;
              return ModuleHomePage(moduleId: id);
            },
          ),
        ],
      ),
    ],
  );
}
