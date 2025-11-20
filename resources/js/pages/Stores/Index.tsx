import { Head, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/app-layout';
import { Store } from '@/types/models';
import { PageProps } from '@/types';
import { Store as StoreIcon, Plus, Edit, Trash2, Save, X } from 'lucide-react';

interface StoresProps extends Record<string, unknown> {
    stores: Store[];
    groups: number[];
}

export default function Index({ auth, stores, groups }: PageProps<StoresProps>) {
    const [isAdding, setIsAdding] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [newStore, setNewStore] = useState({ store: '', group: '' });
    const [editStore, setEditStore] = useState({ store: '', group: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleAdd: FormEventHandler = (e) => {
        e.preventDefault();

        router.post('/stores', {
            store: newStore.store,
            group: newStore.group || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewStore({ store: '', group: '' });
                setIsAdding(false);
                setErrors({});
            },
            onError: (errors) => {
                setErrors(errors);
            },
        });
    };

    const handleEdit = (store: Store) => {
        setEditingId(store.id);
        setEditStore({
            store: store.store,
            group: store.group?.toString() || '',
        });
        setErrors({});
    };

    const handleUpdate: FormEventHandler = (e) => {
        e.preventDefault();

        if (!editingId) return;

        router.put(`/stores/${editingId}`, {
            store: editStore.store,
            group: editStore.group || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingId(null);
                setEditStore({ store: '', group: '' });
                setErrors({});
            },
            onError: (errors) => {
                setErrors(errors);
            },
        });
    };

    const handleDelete = (storeId: number, storeName: string) => {
        if (confirm(`Are you sure you want to delete "${storeName}"?`)) {
            router.delete(`/stores/${storeId}`, {
                preserveScroll: true,
                onError: (errors) => {
                    alert(errors.error || 'Failed to delete store');
                },
            });
        }
    };

    const cancelEdit = () => {
        setEditingId(null);
        setEditStore({ store: '', group: '' });
        setErrors({});
    };

    const cancelAdd = () => {
        setIsAdding(false);
        setNewStore({ store: '', group: '' });
        setErrors({});
    };

    // Group stores by group number
    const groupedStores = stores.reduce((acc, store) => {
        const groupKey = store.group?.toString() || 'No Group';
        if (!acc[groupKey]) {
            acc[groupKey] = [];
        }
        acc[groupKey].push(store);
        return acc;
    }, {} as Record<string, Store[]>);

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Stores Management" />

            <div className="flex flex-1 flex-col gap-6 p-4 pt-0">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Stores Management</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Manage stores and their group assignments
                        </p>
                    </div>
                    <button
                        onClick={() => setIsAdding(true)}
                        disabled={isAdding}
                        className="inline-flex items-center gap-2 justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 transition-colors disabled:opacity-50"
                    >
                        <Plus className="h-4 w-4" />
                        Add Store
                    </button>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="rounded-lg border bg-card p-6">
                        <div className="flex items-center gap-3">
                            <div className="rounded-full bg-primary/10 p-3">
                                <StoreIcon className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Total Stores</p>
                                <p className="text-2xl font-bold">{stores.length}</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-lg border bg-card p-6">
                        <div className="flex items-center gap-3">
                            <div className="rounded-full bg-accent/50 p-3">
                                <StoreIcon className="h-5 w-5 text-accent-foreground" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Groups</p>
                                <p className="text-2xl font-bold">{groups.length}</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-lg border bg-card p-6">
                        <div className="flex items-center gap-3">
                            <div className="rounded-full bg-muted p-3">
                                <StoreIcon className="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-muted-foreground">Without Group</p>
                                <p className="text-2xl font-bold">
                                    {stores.filter(s => !s.group).length}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Stores Table */}
                <div className="rounded-lg border bg-card">
                    <div className="bg-muted/50 px-6 py-4 border-b">
                        <h3 className="text-lg font-semibold">All Stores</h3>
                    </div>

                    <div className="overflow-auto">
                        <table className="w-full caption-bottom text-sm">
                            <thead className="border-b">
                                <tr className="border-b transition-colors hover:bg-muted/50">
                                    <th className="h-12 px-6 text-left align-middle font-medium text-muted-foreground">
                                        Store Name
                                    </th>
                                    <th className="h-12 px-6 text-left align-middle font-medium text-muted-foreground">
                                        Group
                                    </th>
                                    <th className="h-12 px-6 text-right align-middle font-medium text-muted-foreground">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {/* Add New Store Row */}
                                {isAdding && (
                                    <tr className="border-b bg-accent/5">
                                        <td className="p-4">
                                            <input
                                                type="text"
                                                placeholder="Store name"
                                                value={newStore.store}
                                                onChange={(e) => setNewStore({ ...newStore, store: e.target.value })}
                                                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                autoFocus
                                            />
                                            {errors.store && (
                                                <p className="text-xs text-destructive mt-1">{errors.store}</p>
                                            )}
                                        </td>
                                        <td className="p-4">
                                            <input
                                                type="number"
                                                placeholder="Group (optional)"
                                                value={newStore.group}
                                                onChange={(e) => setNewStore({ ...newStore, group: e.target.value })}
                                                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                min="1"
                                            />
                                        </td>
                                        <td className="p-4 text-right">
                                            <div className="flex justify-end gap-2">
                                                <button
                                                    onClick={handleAdd}
                                                    className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90"
                                                >
                                                    <Save className="h-3 w-3" />
                                                    Save
                                                </button>
                                                <button
                                                    onClick={cancelAdd}
                                                    className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-md border hover:bg-accent"
                                                >
                                                    <X className="h-3 w-3" />
                                                    Cancel
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                )}

                                {/* Store Rows */}
                                {stores.length === 0 ? (
                                    <tr>
                                        <td colSpan={3} className="h-24 text-center text-muted-foreground">
                                            No stores found. Click "Add Store" to create one.
                                        </td>
                                    </tr>
                                ) : (
                                    stores.map((store) => (
                                        <tr
                                            key={store.id}
                                            className="border-b transition-colors hover:bg-muted/50"
                                        >
                                            {editingId === store.id ? (
                                                <>
                                                    <td className="p-4">
                                                        <input
                                                            type="text"
                                                            value={editStore.store}
                                                            onChange={(e) => setEditStore({ ...editStore, store: e.target.value })}
                                                            className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                        />
                                                        {errors.store && (
                                                            <p className="text-xs text-destructive mt-1">{errors.store}</p>
                                                        )}
                                                    </td>
                                                    <td className="p-4">
                                                        <input
                                                            type="number"
                                                            value={editStore.group}
                                                            onChange={(e) => setEditStore({ ...editStore, group: e.target.value })}
                                                            className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                            min="1"
                                                        />
                                                    </td>
                                                    <td className="p-4 text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <button
                                                                onClick={handleUpdate}
                                                                className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90"
                                                            >
                                                                <Save className="h-3 w-3" />
                                                                Save
                                                            </button>
                                                            <button
                                                                onClick={cancelEdit}
                                                                className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-md border hover:bg-accent"
                                                            >
                                                                <X className="h-3 w-3" />
                                                                Cancel
                                                            </button>
                                                        </div>
                                                    </td>
                                                </>
                                            ) : (
                                                <>
                                                    <td className="p-4 font-medium">{store.store}</td>
                                                    <td className="p-4">
                                                        {store.group ? (
                                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                                                Group {store.group}
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground text-xs">No group</span>
                                                        )}
                                                    </td>
                                                    <td className="p-4 text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <button
                                                                onClick={() => handleEdit(store)}
                                                                className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-md text-primary hover:bg-primary/10"
                                                            >
                                                                <Edit className="h-3 w-3" />
                                                                Edit
                                                            </button>
                                                            <button
                                                                onClick={() => handleDelete(store.id, store.store)}
                                                                className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-md text-destructive hover:bg-destructive/10"
                                                            >
                                                                <Trash2 className="h-3 w-3" />
                                                                Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </>
                                            )}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
