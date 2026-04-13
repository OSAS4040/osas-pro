import 'package:flutter/material.dart';

import '../../core/offline/offline_policy.dart';
import '../work_orders/presentation/work_orders_home.dart';

class ModuleHomePage extends StatelessWidget {
  const ModuleHomePage({super.key, required this.moduleId});

  final String moduleId;

  static const _titles = <String, String>{
    'dashboard': 'لوحة التحكم',
    'work_orders': 'أوامر العمل',
    'vehicles': 'المركبات',
    'customers': 'العملاء',
    'invoices': 'الفواتير',
    'inventory': 'المخزون',
    'fleet': 'الأسطول',
  };

  @override
  Widget build(BuildContext context) {
    if (moduleId == 'work_orders') {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
            child: Text(
              _titles[moduleId] ?? moduleId,
              style: Theme.of(context).textTheme.headlineSmall,
            ),
          ),
          const Expanded(child: WorkOrdersHome()),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
            child: Text(
              OfflinePolicy.summary,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.outline,
                  ),
            ),
          ),
        ],
      );
    }

    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            _titles[moduleId] ?? moduleId,
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 12),
          Text(
            'هذه شاشة أساس (V1). الوظائف التفصيلية تُبنى لاحقاً مع نفس الصلاحيات القادمة من الخادم.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const Spacer(),
          Text(
            OfflinePolicy.summary,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.outline,
                ),
          ),
        ],
      ),
    );
  }
}
