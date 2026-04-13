import 'package:dio/dio.dart';

import '../../../core/networking/app_dio.dart';
import '../../../session/session_controller.dart';

class WorkOrderListItem {
  const WorkOrderListItem({
    required this.id,
    required this.displayNumber,
    required this.status,
    this.customerName,
    this.vehiclePlate,
  });

  final int id;
  final String displayNumber;
  final String status;
  final String? customerName;
  final String? vehiclePlate;

  static WorkOrderListItem? tryParse(Map<String, dynamic> m) {
    final idVal = m['id'];
    final id = idVal is int ? idVal : int.tryParse(idVal.toString());
    if (id == null) return null;

    final displayNumber =
        (m['work_order_number'] ?? m['order_number'] ?? '#$id').toString();
    final status = (m['status'] ?? '').toString();

    String? customerName;
    final c = m['customer'];
    if (c is Map) {
      customerName = (c['name'] ?? c['name_ar'])?.toString();
    }

    String? vehiclePlate;
    final v = m['vehicle'];
    if (v is Map) {
      vehiclePlate = v['plate_number']?.toString();
    }

    return WorkOrderListItem(
      id: id,
      displayNumber: displayNumber,
      status: status,
      customerName: customerName,
      vehiclePlate: vehiclePlate,
    );
  }
}

class WorkOrdersPageResult {
  const WorkOrdersPageResult({
    required this.items,
    required this.currentPage,
    required this.lastPage,
  });

  final List<WorkOrderListItem> items;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;
}

class WorkOrdersRepository {
  WorkOrdersRepository(SessionController session) : _dio = createAppDio(session);

  final Dio _dio;

  Future<WorkOrdersPageResult> fetchPage({int page = 1}) async {
    final res = await _dio.get<Map<String, dynamic>>(
      '/work-orders',
      queryParameters: {'page': page},
    );
    final body = res.data;
    if (body == null) {
      throw DioException(requestOptions: res.requestOptions, message: 'Empty body');
    }

    final paginator = body['data'];
    if (paginator is! Map) {
      throw DioException(
        requestOptions: res.requestOptions,
        message: 'Unexpected list payload',
      );
    }

    final current = paginator['current_page'];
    final last = paginator['last_page'];
    final currentPage = current is int ? current : int.tryParse('$current') ?? 1;
    final lastPage = last is int ? last : int.tryParse('$last') ?? currentPage;

    final raw = paginator['data'];
    final items = <WorkOrderListItem>[];
    if (raw is List) {
      for (final e in raw) {
        if (e is Map<String, dynamic>) {
          final row = WorkOrderListItem.tryParse(e);
          if (row != null) items.add(row);
        } else if (e is Map) {
          final row = WorkOrderListItem.tryParse(Map<String, dynamic>.from(e));
          if (row != null) items.add(row);
        }
      }
    }

    return WorkOrdersPageResult(
      items: items,
      currentPage: currentPage,
      lastPage: lastPage,
    );
  }
}
