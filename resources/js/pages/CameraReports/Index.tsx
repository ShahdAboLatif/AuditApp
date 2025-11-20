import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/app-layout';
import { Audit, Entity, Store, PaginatedData } from '@/types/models';
import { PageProps } from '@/types';
import { Filter, Download, RefreshCw } from 'lucide-react';

interface ReportData {
    audits: PaginatedData<Audit>;
    entities: Entity[];
}

interface CameraReportsProps extends Record<string, unknown> {
    reportData: ReportData;
    stores: Store[];
    groups: number[];
    filters: {
        store_id?: number;
        group?: number;
        date_from?: string;
        date_to?: string;
        report_type?: 'main' | 'secondary' | '';
        date_range_type?: 'daily' | 'weekly';
    };
}

export default function Index({
    auth,
    reportData,
    stores,
    groups,
    filters
}: PageProps<CameraReportsProps>) {
    const [dateRangeType, setDateRangeType] = useState<'daily' | 'weekly'>(
        filters.date_range_type || 'daily'
    );
    const [reportType, setReportType] = useState<'main' | 'secondary' | ''>(
        filters.report_type || ''
    );
    const [storeId, setStoreId] = useState<number | string>(filters.store_id || '');
    const [group, setGroup] = useState<number | string>(filters.group || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');

    const applyFilters = () => {
        router.get(
            '/camera-reports',
            {
                date_range_type: dateRangeType,
                report_type: reportType,
                store_id: storeId,
                group: group,
                date_from: dateFrom,
                date_to: dateTo,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const resetFilters = () => {
        setDateRangeType('daily');
        setReportType('');
        setStoreId('');
        setGroup('');
        setDateFrom('');
        setDateTo('');
        router.get('/camera-reports', { date_range_type: 'daily' });
    };

    const handleExport = () => {
        // TODO: Implement export functionality
        alert('Export feature coming soon!');
    };

    // Helper function to get rating for a specific entity in an audit
    const getRatingForEntity = (audit: Audit, entityId: number): string => {
        const cameraForm = audit.camera_forms?.find(cf => cf.entity_id === entityId);
        return cameraForm?.rating?.label || '-';
    };

    const { audits, entities } = reportData;

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Camera Reports" />

            <div className="flex flex-1 flex-col gap-6 p-4 pt-0">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Camera Reports</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            View and analyze camera inspection reports
                        </p>
                    </div>
                    <button
                        onClick={handleExport}
                        className="inline-flex items-center gap-2 justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 transition-colors"
                    >
                        <Download className="h-4 w-4" />
                        Export
                    </button>
                </div>

                {/* Filters Card */}
                <div className="rounded-lg border bg-card">
                    <div className="bg-gradient-to-r from-primary/10 to-accent/10 px-6 py-4 border-b">
                        <div className="flex items-center gap-2">
                            <Filter className="h-5 w-5" />
                            <h3 className="text-lg font-semibold">Filters</h3>
                        </div>
                    </div>

                    <div className="p-6 space-y-4">
                        {/* Row 1: Date Range Type & Report Type */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Date Range Type</label>
                                <select
                                    value={dateRangeType}
                                    onChange={(e) => setDateRangeType(e.target.value as 'daily' | 'weekly')}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                </select>
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Report Type</label>
                                <select
                                    value={reportType}
                                    onChange={(e) => setReportType(e.target.value as 'main' | 'secondary' | '')}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="">All Types</option>
                                    <option value="main">Main</option>
                                    <option value="secondary">Secondary</option>
                                </select>
                            </div>
                        </div>

                        {/* Row 2: Store & Group */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Store</label>
                                <select
                                    value={storeId}
                                    onChange={(e) => setStoreId(e.target.value)}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="">All Stores</option>
                                    {stores.map((store) => (
                                        <option key={store.id} value={store.id}>
                                            {store.store}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Group</label>
                                <select
                                    value={group}
                                    onChange={(e) => setGroup(e.target.value)}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="">All Groups</option>
                                    {groups.map((g) => (
                                        <option key={g} value={g}>
                                            Group {g}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Row 3: Date Range */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Start Date</label>
                                <input
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) => setDateFrom(e.target.value)}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                />
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">End Date</label>
                                <input
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                />
                            </div>
                        </div>

                        {/* Action Buttons */}
                        <div className="flex gap-2 pt-2">
                            <button
                                onClick={applyFilters}
                                className="inline-flex items-center gap-2 justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
                            >
                                <RefreshCw className="h-4 w-4" />
                                Apply Filters
                            </button>
                            <button
                                onClick={resetFilters}
                                className="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                {/* Results Summary */}
                <div className="rounded-lg border bg-card p-4">
                    <p className="text-sm text-muted-foreground">
                        Showing <span className="font-semibold text-foreground">{audits.data.length}</span> results
                        {entities.length > 0 && (
                            <> with <span className="font-semibold text-foreground">{entities.length}</span> entities</>
                        )}
                    </p>
                </div>

                {/* Report Table */}
                <div className="rounded-lg border bg-card overflow-hidden">
                    <div className="overflow-auto">
                        <table className="w-full caption-bottom text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground whitespace-nowrap sticky left-0 bg-muted/50">
                                        Date
                                    </th>
                                    <th className="h-12 px-4 text-left align-middle font-medium text-muted-foreground whitespace-nowrap sticky left-0 bg-muted/50">
                                        Store
                                    </th>
                                    {/* Dynamic Entity Columns */}
                                    {entities.map((entity) => (
                                        <th
                                            key={entity.id}
                                            className="h-12 px-4 text-center align-middle font-medium text-muted-foreground whitespace-nowrap"
                                            title={entity.entity_label}
                                        >
                                            <div className="flex flex-col items-center gap-1">
                                                <span>{entity.entity_label}</span>
                                                {entity.category && (
                                                    <span className="text-xs font-normal text-muted-foreground">
                                                        {entity.category.label}
                                                    </span>
                                                )}
                                            </div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {audits.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={entities.length + 2}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            No reports found. Try adjusting your filters.
                                        </td>
                                    </tr>
                                ) : (
                                    audits.data.map((audit) => (
                                        <tr
                                            key={audit.id}
                                            className="border-b transition-colors hover:bg-muted/50"
                                        >
                                            <td className="p-4 align-middle whitespace-nowrap font-medium">
                                                {new Date(audit.date).toLocaleDateString()}
                                            </td>
                                            <td className="p-4 align-middle whitespace-nowrap">
                                                {audit.store?.store}
                                            </td>
                                            {/* Dynamic Entity Values */}
                                            {entities.map((entity) => {
                                                const rating = getRatingForEntity(audit, entity.id);
                                                return (
                                                    <td
                                                        key={entity.id}
                                                        className="p-4 align-middle text-center"
                                                    >
                                                        {rating !== '-' ? (
                                                            <span className="inline-flex items-center justify-center px-2.5 py-1 text-xs font-semibold rounded-full bg-primary text-primary-foreground">
                                                                {rating}
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground">-</span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {audits.links && audits.links.length > 3 && (
                        <div className="flex items-center justify-center gap-2 p-4 border-t">
                            {audits.links.map((link, index) => (
                                <button
                                    key={index}
                                    onClick={() => link.url && router.visit(link.url)}
                                    disabled={!link.url}
                                    className={`inline-flex items-center justify-center rounded-md px-3 py-1 text-sm font-medium transition-colors ${
                                        link.active
                                            ? 'bg-primary text-primary-foreground'
                                            : 'hover:bg-accent hover:text-accent-foreground'
                                    } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
