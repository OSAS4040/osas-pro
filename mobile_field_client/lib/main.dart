import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'auth/data/auth_repository.dart';
import 'root_host.dart';
import 'routing/app_router.dart';
import 'session/session_controller.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final session = SessionController();
  await session.restore();

  final authRepo = AuthRepository(session);
  final router = createAppRouter(session);

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: session),
        Provider.value(value: authRepo),
      ],
      child: RootHost(
        session: session,
        authRepo: authRepo,
        router: router,
      ),
    ),
  );
}
