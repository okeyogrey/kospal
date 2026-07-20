import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Banknote,
    Building2,
    ChartColumn,
    ClipboardCheck,
    Clock,
    Coins,
    ContactRound,
    CreditCard,
    FileSpreadsheet,
    FileText,
    History,
    Landmark,
    LayoutGrid,
    Package,
    PackageCheck,
    Receipt,
    Settings2,
    ShoppingBasket,
    ShoppingCart,
    Tags,
    Truck,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/hooks/use-translations';
import { filterNavItems } from '@/lib/navigation';
import { dashboard } from '@/routes';
import { index as branches } from '@/routes/branches';
import { index as categories } from '@/routes/categories';
import { index as expenses } from '@/routes/expenses';
import { index as inventory } from '@/routes/inventory';
import { index as productivity } from '@/routes/productivity';
import { index as products } from '@/routes/products';
import { index as reports } from '@/routes/reports';
import { index as customers } from '@/routes/customers';
import { index as sales } from '@/routes/sales';
import { index as staff } from '@/routes/staff';
import { index as shifts } from '@/routes/shifts';
import { index as stockTransfers } from '@/routes/stock-transfers';
import { index as subscription } from '@/routes/subscription';
import { index as suppliers } from '@/routes/suppliers';
import { edit as editProfile } from '@/routes/profile';
import type { NavGroup, NavItem } from '@/types';

export function AppSidebar() {
    const { t } = useTranslations();
    const { auth, navigation, workspace } = usePage().props;
    const features = workspace.limits?.features ?? [];
    const canTransfer = features.includes('stock_transfers');
    const canPurchaseOrders = features.includes('purchase_orders');
    const canCustomerCredit = features.includes('customer_credit');
    const canStockCounts = features.includes('stock_counts');
    const isPlatformAdmin = auth.role === 'platform_super_admin';

    const platformItems: NavItem[] = [
        {
            key: 'platform_subscriptions',
            title: t('nav.platform_subscriptions'),
            href: '/platform/subscription-requests',
            icon: CreditCard,
        },
        {
            key: 'platform_payment_instructions',
            title: t('nav.platform_payment_instructions'),
            href: '/platform/payment-instructions',
            icon: Landmark,
        },
    ];

    const operationsItems: NavItem[] = [
        {
            key: 'dashboard',
            title: t('nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            key: 'products',
            title: t('nav.products'),
            href: products(),
            icon: Package,
        },
        {
            key: 'categories',
            title: t('nav.categories'),
            href: categories(),
            icon: Tags,
        },
        {
            key: 'inventory',
            title: t('nav.inventory'),
            href: inventory(),
            icon: Warehouse,
        },
        {
            key: 'inventory-timeline',
            title: t('nav.inventory_timeline'),
            href: '/inventory/timeline',
            icon: History,
        },
        {
            key: 'inventory-valuation',
            title: t('nav.inventory_valuation'),
            href: '/inventory/valuation',
            icon: Coins,
        },
        ...(canTransfer
            ? [
                  {
                      key: 'transfers' as const,
                      title: t('nav.transfers'),
                      href: stockTransfers(),
                      icon: ArrowLeftRight,
                  },
              ]
            : []),
        ...(canPurchaseOrders
            ? [
                  {
                      key: 'purchase-orders' as const,
                      title: t('nav.purchase_orders'),
                      href: '/purchase-orders',
                      icon: ShoppingBasket,
                  },
                  {
                      key: 'goods-received' as const,
                      title: t('nav.goods_received'),
                      href: '/goods-received',
                      icon: PackageCheck,
                  },
                  {
                      key: 'supplier-invoices' as const,
                      title: t('nav.supplier_invoices'),
                      href: '/supplier-invoices',
                      icon: FileText,
                  },
                  {
                      key: 'supplier-payments' as const,
                      title: t('nav.supplier_payments'),
                      href: '/supplier-payments',
                      icon: Wallet,
                  },
              ]
            : []),
        ...(canStockCounts
            ? [
                  {
                      key: 'stock-counts' as const,
                      title: t('nav.stock_counts'),
                      href: '/stock-counts',
                      icon: ClipboardCheck,
                  },
              ]
            : []),
        {
            key: 'sales',
            title: t('nav.sales'),
            href: sales(),
            icon: ShoppingCart,
        },
        {
            key: 'customers',
            title: t('nav.customers'),
            href: customers(),
            icon: ContactRound,
        },
        ...(canCustomerCredit
            ? [
                  {
                      key: 'customer-payments' as const,
                      title: t('nav.customer_payments', 'Customer payments'),
                      href: '/customer-payments',
                      icon: Wallet,
                  },
              ]
            : []),
        {
            key: 'expenses',
            title: t('nav.expenses'),
            href: expenses(),
            icon: Receipt,
        },
        {
            key: 'suppliers',
            title: t('nav.suppliers'),
            href: suppliers(),
            icon: Truck,
        },
        {
            key: 'reports',
            title: t('nav.reports'),
            href: reports(),
            icon: ChartColumn,
        },
        {
            key: 'productivity',
            title: t('nav.productivity', 'Productivity'),
            href: productivity(),
            icon: FileSpreadsheet,
        },
    ];

    const organizationItems: NavItem[] = [
        {
            key: 'staff',
            title: t('nav.staff'),
            href: staff(),
            icon: Users,
        },
        {
            key: 'shifts',
            title: t('nav.shifts'),
            href: shifts(),
            icon: Clock,
        },
        {
            key: 'cash-sessions',
            title: t('nav.cash_sessions', 'Cash management'),
            href: '/cash-sessions',
            icon: Banknote,
        },
        {
            key: 'branches',
            title: t('nav.branches'),
            href: branches(),
            icon: Building2,
        },
        {
            key: 'subscription',
            title: t('nav.subscription'),
            href: subscription(),
            icon: CreditCard,
        },
        {
            key: 'settings',
            title: t('nav.settings'),
            href: editProfile(),
            icon: Settings2,
        },
    ];

    const groups: NavGroup[] = [
        ...(isPlatformAdmin
            ? [
                  {
                      label: t('nav.platform'),
                      items: filterNavItems(
                          platformItems,
                          navigation.allowedKeys,
                          auth.role,
                      ),
                  },
              ]
            : []),
        {
            label: t('nav.operations'),
            items: filterNavItems(
                operationsItems,
                navigation.allowedKeys,
                auth.role,
            ),
        },
        {
            label: t('nav.organization'),
            items: filterNavItems(
                organizationItems,
                navigation.allowedKeys,
                auth.role,
            ),
        },
    ].filter((group) => group.items.length > 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={
                                    isPlatformAdmin
                                        ? '/platform/subscription-requests'
                                        : dashboard()
                                }
                                prefetch
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={groups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
