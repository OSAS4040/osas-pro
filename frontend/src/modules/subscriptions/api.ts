import apiClient from '@/lib/apiClient'

export const subscriptionsApi = {
  getCurrent: () => apiClient.get('/subscriptions/current'),
  getPlans: () => apiClient.get('/subscriptions/plans'),
  getInvoices: () => apiClient.get('/subscriptions/invoices'),
  getWallet: () => apiClient.get('/subscriptions/wallet'),
  upgrade: (planSlug: string) => apiClient.post('/subscriptions/upgrade', { plan_slug: planSlug }),
  downgrade: (planSlug: string) => apiClient.post('/subscriptions/downgrade', { plan_slug: planSlug }),
  notifications: (afterId = 0) => apiClient.get('/subscriptions/notifications', { params: { after_id: afterId } }),
  listPaymentOrders: () => apiClient.get('/subscriptions/payment-orders'),
  createPaymentOrder: (planId: number) => apiClient.post('/subscriptions/payment-orders', { plan_id: planId }),
  submitTransfer: (orderId: number, payload: Record<string, unknown>) =>
    apiClient.post(`/subscriptions/payment-orders/${orderId}/submit-transfer`, payload),
  uploadReceipt: (orderId: number, formData: FormData) =>
    apiClient.post(`/subscriptions/payment-orders/${orderId}/upload-receipt`, formData),

  adminOverview: () => apiClient.get('/admin/subscriptions/overview'),
  adminReviewQueue: () => apiClient.get('/admin/subscriptions/review-queue'),
  adminTransactions: () => apiClient.get('/admin/subscriptions/transactions'),
  adminWallets: () => apiClient.get('/admin/subscriptions/wallets'),
  adminInsights: () => apiClient.get('/admin/subscriptions/insights'),
  adminNotifications: () => apiClient.get('/admin/subscriptions/notifications'),
  adminSubscriptionAttentionSummary: () => apiClient.get('/admin/subscriptions/attention-summary'),
  adminSubscriptionList: (params?: Record<string, unknown>) =>
    apiClient.get('/admin/subscriptions/list', { params }),
  adminSubscriptionDetail: (subscriptionId: number) =>
    apiClient.get(`/admin/subscriptions/subscription/${subscriptionId}`),
  adminPaymentOrderDetail: (id: number) => apiClient.get(`/admin/subscriptions/payment-orders/${id}`),
  adminSubscriptionInvoices: (params?: Record<string, unknown>) =>
    apiClient.get('/admin/subscriptions/invoices', { params }),
  adminSubscriptionInvoiceDetail: (id: number) => apiClient.get(`/admin/subscriptions/invoices/${id}`),
  adminBankTransactionDetail: (id: number) => apiClient.get(`/admin/subscriptions/bank-transactions/${id}`),
  adminMatch: (paymentOrderId: number, bankTransactionId: number) =>
    apiClient.post(`/admin/subscriptions/review-queue/${paymentOrderId}/match`, { bank_transaction_id: bankTransactionId }),
  adminApprove: (paymentOrderId: number) => apiClient.post(`/admin/subscriptions/payment-orders/${paymentOrderId}/approve`),
  adminReject: (paymentOrderId: number, reason: string) =>
    apiClient.post(`/admin/subscriptions/review-queue/${paymentOrderId}/reject`, { reason }),
}

