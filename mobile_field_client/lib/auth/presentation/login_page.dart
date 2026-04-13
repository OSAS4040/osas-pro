import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../session/session_controller.dart';
import '../data/auth_repository.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _identifier = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _identifier.dispose();
    _password.dispose();
    super.dispose();
  }

  String _mapError(Object e) {
    if (e is DioException) {
      final data = e.response?.data;
      if (data is Map && data['message'] is String) {
        return data['message'] as String;
      }
      switch (e.type) {
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.receiveTimeout:
        case DioExceptionType.sendTimeout:
          return 'انتهت مهلة الاتصال. تحقق من الشبكة ثم أعد المحاولة.';
        case DioExceptionType.connectionError:
          return 'تعذّر الاتصال بالخادم. تحقق من الإنترنت وعنوان الـ API.';
        default:
          break;
      }
    }
    return 'تعذّر إكمال العملية. حاول مرة أخرى.';
  }

  Future<void> _submit() async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final id = _identifier.text.trim();
    final pass = _password.text;
    if (id.isEmpty || pass.isEmpty) {
      setState(() {
        _busy = false;
        _error = 'يرجى إدخال البريد أو الجوال وكلمة المرور.';
      });
      return;
    }

    final repo = context.read<AuthRepository>();
    final session = context.read<SessionController>();

    try {
      final json = await repo.login(identifier: id, password: pass);
      if (!mounted) return;
      if (json['otp_required'] == true) {
        setState(() {
          _busy = false;
          _error = 'ميزة رمز التحقق عبر البريد غير مفعّلة في التطبيق بعد. سجّل الدخول من الويب أو عطّل OTP على الخادم.';
        });
        return;
      }
      await session.applyLoginResponse(Map<String, dynamic>.from(json));
      if (!mounted) return;
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = _mapError(e);
      });
      return;
    }

    if (!mounted) return;
    setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('تسجيل الدخول')),
      body: SafeArea(
        child: Align(
          alignment: Alignment.topCenter,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'أدخل بريدك الإلكتروني أو رقم جوالك',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _identifier,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [AutofillHints.email, AutofillHints.telephoneNumber],
                    decoration: const InputDecoration(
                      labelText: 'البريد أو الجوال',
                      hintText: 'name@example.com أو 05xxxxxxxx',
                    ),
                    enabled: !_busy,
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _password,
                    obscureText: true,
                    textInputAction: TextInputAction.done,
                    onSubmitted: (_) => _submit(),
                    decoration: const InputDecoration(labelText: 'كلمة المرور'),
                    enabled: !_busy,
                  ),
                  const SizedBox(height: 16),
                  if (_error != null)
                    Text(
                      _error!,
                      style: TextStyle(color: Theme.of(context).colorScheme.error),
                    ),
                  const SizedBox(height: 8),
                  FilledButton(
                    onPressed: _busy ? null : _submit,
                    child: _busy
                        ? const SizedBox(
                            height: 22,
                            width: 22,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('تسجيل الدخول'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
