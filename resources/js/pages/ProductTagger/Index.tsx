import { Head } from '@inertiajs/react';
import { FormEvent, useState, useCallback } from 'react';
import { PolarisProvider } from '@/components/polaris-provider';
import {
    Page,
    Card,
    FormLayout,
    TextField,
    Select,
    Button,
    Banner,
    Layout,
    Badge,
    Text,
    BlockStack,
    InlineStack,
} from '@shopify/polaris';

interface Collection {
    id: string;
    title: string;
    handle: string;
}

interface Product {
    id: string;
    title: string;
    tags: string[];
}

interface PreviewResponse {
    count: number;
    products: Product[];
}

interface ApplyResponse {
    success: boolean;
    stats: {
        total: number;
        updated: number;
        skipped: number;
        failed: number;
    };
}

interface Props {
    collections: Collection[];
}

function ProductTaggerContent({ collections }: Props) {
    const [filters, setFilters] = useState({
        keyword: '',
        product_type: '',
        collection_id: '',
    });
    const [tag, setTag] = useState('');
    const [preview, setPreview] = useState<PreviewResponse | null>(null);
    const [isLoadingPreview, setIsLoadingPreview] = useState(false);
    const [isApplying, setIsApplying] = useState(false);
    const [result, setResult] = useState<ApplyResponse | null>(null);
    const [error, setError] = useState<string | null>(null);

    const collectionOptions = [
        { label: 'All Collections', value: '' },
        ...collections.map((collection) => ({
            label: collection.title,
            value: collection.id,
        })),
    ];

    const handlePreview = useCallback(async (e: FormEvent) => {
        e.preventDefault();
        setError(null);
        setPreview(null);
        setResult(null);
        setIsLoadingPreview(true);

        try {
            const response = await fetch(route('product-tagger.preview'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
                },
                body: JSON.stringify({ filters }),
            });

            if (!response.ok) {
                throw new Error('Failed to fetch preview');
            }

            const data = await response.json();
            setPreview(data);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setIsLoadingPreview(false);
        }
    }, [filters]);

    const handleApplyTag = useCallback(async () => {
        if (!tag.trim()) {
            setError('Please enter a tag');
            return;
        }

        setError(null);
        setResult(null);
        setIsApplying(true);

        try {
            const response = await fetch(route('product-tagger.apply-tag'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
                },
                body: JSON.stringify({ filters, tag: tag.trim() }),
            });

            if (!response.ok) {
                throw new Error('Failed to apply tag');
            }

            const data = await response.json();
            setResult(data);
            setPreview(null);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setIsApplying(false);
        }
    }, [filters, tag]);

    return (
        <Page
            title="Bulk Product Tagger"
            subtitle="Filter products and add tags in bulk"
            backAction={{ content: 'Dashboard', url: '/dashboard' }}
        >
            <Layout>
                {error && (
                    <Layout.Section>
                        <Banner title="Error" tone="critical" onDismiss={() => setError(null)}>
                            <p>{error}</p>
                        </Banner>
                    </Layout.Section>
                )}

                {result && (
                    <Layout.Section>
                        <Banner title="Success!" tone="success" onDismiss={() => setResult(null)}>
                            <BlockStack gap="200">
                                <Text as="p">
                                    <strong>{result.stats.total}</strong> products processed
                                </Text>
                                <Text as="p" tone="success">
                                    <strong>{result.stats.updated}</strong> updated
                                </Text>
                                <Text as="p">
                                    <strong>{result.stats.skipped}</strong> already had tag
                                </Text>
                                {result.stats.failed > 0 && (
                                    <Text as="p" tone="critical">
                                        <strong>{result.stats.failed}</strong> failed
                                    </Text>
                                )}
                            </BlockStack>
                        </Banner>
                    </Layout.Section>
                )}

                <Layout.Section>
                    <Card>
                        <BlockStack gap="400">
                            <Text as="h2" variant="headingMd">
                                Filters
                            </Text>
                            <Text as="p" tone="subdued">
                                Choose one or combine multiple filters
                            </Text>

                            <form onSubmit={handlePreview}>
                                <FormLayout>
                                    <FormLayout.Group condensed>
                                        <TextField
                                            label="Keyword (Title Contains)"
                                            value={filters.keyword}
                                            onChange={(value) =>
                                                setFilters({ ...filters, keyword: value })
                                            }
                                            placeholder="e.g., Shirt"
                                            autoComplete="off"
                                        />

                                        <TextField
                                            label="Product Type"
                                            value={filters.product_type}
                                            onChange={(value) =>
                                                setFilters({ ...filters, product_type: value })
                                            }
                                            placeholder="e.g., Apparel"
                                            autoComplete="off"
                                        />

                                        <Select
                                            label="Collection"
                                            options={collectionOptions}
                                            value={filters.collection_id}
                                            onChange={(value) =>
                                                setFilters({ ...filters, collection_id: value })
                                            }
                                        />
                                    </FormLayout.Group>

                                    <TextField
                                        label="Tag to Apply"
                                        value={tag}
                                        onChange={setTag}
                                        placeholder="e.g., Free Ship"
                                        autoComplete="off"
                                    />

                                    <InlineStack gap="300">
                                        <Button
                                            submit
                                            loading={isLoadingPreview}
                                            disabled={isApplying}
                                        >
                                            Preview Matches
                                        </Button>

                                        <Button
                                            variant="primary"
                                            onClick={handleApplyTag}
                                            loading={isApplying}
                                            disabled={
                                                !preview ||
                                                !tag.trim() ||
                                                isLoadingPreview
                                            }
                                        >
                                            Apply Tag
                                        </Button>
                                    </InlineStack>
                                </FormLayout>
                            </form>
                        </BlockStack>
                    </Card>
                </Layout.Section>

                {preview && (
                    <Layout.Section>
                        <Card>
                            <BlockStack gap="400">
                                <Text as="h2" variant="headingMd">
                                    Preview Results
                                </Text>
                                <Text as="p" tone="subdued">
                                    Found {preview.count} matching products (showing first 10)
                                </Text>

                                {preview.products.length === 0 ? (
                                    <Text as="p" tone="subdued">
                                        No products match your filters
                                    </Text>
                                ) : (
                                    <div style={{ maxHeight: '500px', overflowY: 'auto', padding: '4px' }}>
                                        <BlockStack gap="300">
                                            {preview.products.map((product) => (
                                                <Card key={product.id}>
                                                    <BlockStack gap="200">
                                                        <Text as="h3" variant="bodyMd" fontWeight="semibold">
                                                            {product.title}
                                                        </Text>
                                                        {product.tags.length > 0 && (
                                                            <InlineStack gap="100">
                                                                {product.tags.map((t, i) => (
                                                                    <Badge key={i}>{t}</Badge>
                                                                ))}
                                                            </InlineStack>
                                                        )}
                                                    </BlockStack>
                                                </Card>
                                            ))}
                                        </BlockStack>
                                    </div>
                                )}
                            </BlockStack>
                        </Card>
                    </Layout.Section>
                )}
            </Layout>
        </Page>
    );
}

export default function ProductTaggerIndex(props: Props) {
    return (
        <>
            <Head title="Bulk Product Tagger" />
            <PolarisProvider>
                <ProductTaggerContent {...props} />
            </PolarisProvider>
        </>
    );
}

// Simple layout without authentication requirement
ProductTaggerIndex.layout = (page: React.ReactNode) => (
    <div style={{ minHeight: '100vh', backgroundColor: '#ffffffff', display: 'flex', flexDirection: 'column' }}>
        <div style={{ flex: 1 }}>{page}</div>
        <footer style={{ 
            backgroundColor: '#0e7490', 
            color: 'white', 
            padding: '20px', 
            textAlign: 'center',
            marginTop: '40px'
        }}>
            <p style={{ margin: 0, fontSize: '14px' }}>
                © 2025 Shopify Bulk Product Tagger | Built with Laravel, React & Polaris
            </p>
        </footer>
    </div>
);
