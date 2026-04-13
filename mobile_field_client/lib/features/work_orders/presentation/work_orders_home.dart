import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../session/session_controller.dart';
import '../data/work_orders_repository.dart';

String workOrderStatusLabelAr(String status) {
  switch (status) {
    case 'draft':
      return 'مسودة';
    case 'pending_manager_approval':
      return 'بانتظار موافقة المدير';
    case 'approved':
      return 'معتمد';
    case 'cancellation_requested':
      return 'طلب إلغاء';
    case 'in_progress':
      return 'قيد التنفيذ';
    case 'on_hold':
      return 'معلّق';
    case 'completed':
      return 'مكتمل';
    case 'delivered':
      return 'مسلّم';
    case 'cancelled':
      return 'ملغى';
    default:
      return status;
  }
}

class WorkOrdersHome extends StatefulWidget {
  const WorkOrdersHome({super.key});

  @override
  State<WorkOrdersHome> createState() => _WorkOrdersHomeState();
}

class _WorkOrdersHomeState extends State<WorkOrdersHome> {
  WorkOrdersRepository? _repo;
  final List<WorkOrderListItem> _items = [];
  int _page = 0;
  int _lastPage = 1;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  WorkOrdersRepository _repository(BuildContext context) {
    return _repo ??= WorkOrdersRepository(context.read<SessionController>());
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
          return 'انتهت مهلة الاتصال.';
        case DioExceptionType.connectionError:
          return 'تعذّر الاتصال بالخادم.';
        default:
          break;
      }
    }
    return 'تعذّر تحميل القائمة.';
  }

  Future<void> _loadFirst() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final r = await _repository(context).fetchPage(page: 1);
      if (!mounted) return;
      setState(() {
        _items
          ..clear()
          ..addAll(r.items);
        _page = r.currentPage;
        _lastPage = r.lastPage;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = _mapError(e);
      });
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || _page >= _lastPage) return;
    setState(() => _loadingMore = true);
    try {
      final next = _page + 1;
      final r = await _repository(context).fetchPage(page: next);
      if (!mounted) return;
      setState(() {
        _items.addAll(r.items);
        _page = r.currentPage;
        _lastPage = r.lastPage;
        _loadingMore = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _loadingMore = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_mapError(e))),
      );
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadFirst());
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _loadFirst,
                child: const Text('إعادة المحاولة'),
              ),
            ],
          ),
        ),
      );
    }

    if (_items.isEmpty) {
      return RefreshIndicator(
        onRefresh: _loadFirst,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 120),
            Center(child: Text('لا توجد أوامر عمل في هذه الصفحة.')),
          ],
        ),
      );
    }

    final trailing = _loadingMore || _page < _lastPage ? 1 : 0;

    return RefreshIndicator(
      onRefresh: _loadFirst,
      child: ListView.builder(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: _items.length + trailing,
        itemBuilder: (context, index) {
          if (index == _items.length) {
            if (_loadingMore) {
              return const Padding(
                padding: EdgeInsets.all(16),
                child: Center(child: CircularProgressIndicator()),
              );
            }
            return TextButton(
              onPressed: _loadMore,
              child: const Text('تحميل المزيد'),
            );
          }

          final wo = _items[index];
          final subtitle = [
            workOrderStatusLabelAr(wo.status),
            if (wo.customerName != null && wo.customerName!.isNotEmpty) wo.customerName,
            if (wo.vehiclePlate != null && wo.vehiclePlate!.isNotEmpty) wo.vehiclePlate,
          ].join(' · ');

          return ListTile(
            leading: const Icon(Icons.build_circle_outlined),
            title: Text(wo.displayNumber),
            subtitle: Text(subtitle),
          );
        },
      ),
    );
  }
}
