<?php

declare(strict_types=1);

namespace App\Reporting\Operations;

/**
 * Normalizes raw feed rows into the public API contract (rule-based copy, no AI).
 */
final class OperationFeedItemPresenter
{
    public function __construct(
        private readonly OperationFeedEntityRouteResolver $routes,
    ) {}

    /**
     * @param  array<string, mixed>  $row  joined feed row
     * @return array<string, mixed>
     */
    public function present(array $row, bool $financialVisible): array
    {
        $type = (string) $row['feed_type'];
        $id = (int) $row['entity_id'];
        $linkId = isset($row['link_id']) ? (int) $row['link_id'] : null;
        if ($linkId < 1) {
            $linkId = null;
        }

        $titles = $this->titles($type, $row);
        $tags = $this->tags($type, (string) $row['status'], (string) $row['attention_level']);

        $amount = null;
        $currency = null;
        if ($financialVisible && ($type === 'invoice' || $type === 'payment')) {
            if (isset($row['amount']) && $row['amount'] !== null) {
                $amount = (float) $row['amount'];
            }
            if (! empty($row['currency'])) {
                $currency = (string) $row['currency'];
            }
        }

        $financialVisibilityApplied = ! $financialVisible && in_array($type, ['invoice', 'payment'], true);

        return [
            'type'                         => $type,
            'id'                           => $id,
            'occurred_at'                  => $row['occurred_at'] ? (string) $row['occurred_at'] : null,
            'title'                        => $titles['title'],
            'subtitle'                     => $titles['subtitle'],
            'description'                  => $titles['description'],
            'status'                       => (string) $row['status'],
            'severity'                     => (string) $row['attention_level'],
            'attention_level'              => (string) $row['attention_level'],
            'company_id'                   => (int) $row['company_id'],
            'company_name'                 => (string) ($row['company_name'] ?? ''),
            'branch_id'                    => $row['branch_id'] !== null ? (int) $row['branch_id'] : null,
            'branch_name'                  => $row['branch_name'] !== null ? (string) $row['branch_name'] : null,
            'customer_id'                  => $row['customer_id'] !== null ? (int) $row['customer_id'] : null,
            'customer_name'                => $row['customer_name'] !== null ? (string) $row['customer_name'] : null,
            'actor_user_id'                => $row['actor_user_id'] !== null ? (int) $row['actor_user_id'] : null,
            'actor_name'                   => $row['actor_name'] !== null ? (string) $row['actor_name'] : null,
            'amount'                       => $amount,
            'currency'                     => $currency,
            'reference'                    => (string) ($row['reference'] ?? ''),
            'entity_route'                 => $this->routes->resolve($type, $id, $linkId),
            'tags'                         => $tags,
            'financial_visibility_applied' => $financialVisibilityApplied,
            'read_only'                    => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{title: string, subtitle: string, description: ?string}
     */
    private function titles(string $type, array $row): array
    {
        $ref = (string) ($row['reference'] ?? '');
        $cust = (string) ($row['customer_name'] ?? '');

        return match ($type) {
            'work_order' => [
                'title'       => 'Work order activity',
                'subtitle'    => $ref !== '' ? $ref : 'Work order #'.$row['entity_id'],
                'description' => $cust !== '' ? 'Customer: '.$cust : null,
            ],
            'invoice' => [
                'title'       => 'Invoice issued',
                'subtitle'    => $ref !== '' ? $ref : 'Invoice #'.$row['entity_id'],
                'description' => $cust !== '' ? 'Customer: '.$cust : null,
            ],
            'payment' => [
                'title'       => 'Payment recorded',
                'subtitle'    => $ref !== '' ? $ref : 'Payment #'.$row['entity_id'],
                'description' => null,
            ],
            'ticket' => [
                'title'       => 'Support ticket update',
                'subtitle'    => $ref !== '' ? $ref : 'Ticket #'.$row['entity_id'],
                'description' => $cust !== '' ? 'Customer: '.$cust : null,
            ],
            default => [
                'title'       => 'Operational event',
                'subtitle'    => $ref,
                'description' => null,
            ],
        };
    }

    /**
     * @return list<string>
     */
    private function tags(string $type, string $status, string $attentionLevel): array
    {
        $tags = ['new'];
        if ($type === 'work_order') {
            $tags = match ($status) {
                'completed', 'delivered' => ['closed', 'completed'],
                'draft', 'pending_manager_approval' => ['open', 'new'],
                'cancellation_requested' => ['watch', 'cancellation'],
                default => ['open'],
            };
        }
        if ($type === 'invoice') {
            $tags = ['invoice'];
            if (in_array($status, ['paid', 'settled'], true)) {
                $tags[] = 'paid';
            } elseif (in_array($status, ['pending', 'partial'], true)) {
                $tags[] = 'unpaid';
            }
            if ($attentionLevel === 'important' || $attentionLevel === 'critical') {
                $tags[] = 'overdue';
            }
        }
        if ($type === 'payment') {
            $tags = ['payment', 'completed'];
        }
        if ($type === 'ticket') {
            $tags = in_array($status, ['resolved', 'closed'], true) ? ['closed'] : ['open'];
            if ($status === 'escalated') {
                $tags[] = 'escalated';
            }
            if ($attentionLevel === 'important' || $attentionLevel === 'critical') {
                $tags[] = 'overdue';
            }
        }

        return array_values(array_unique($tags));
    }
}

