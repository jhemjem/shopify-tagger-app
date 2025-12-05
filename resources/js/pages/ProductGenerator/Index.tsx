import { Head } from '@inertiajs/react';
import { FormEvent, useState, useCallback } from 'react';
import { PolarisProvider } from '@/components/polaris-provider';
import {
    Page,
    Card,
    FormLayout,
    TextField,
    Button,
    Banner,
    Layout,
    Text,
    BlockStack,
    InlineStack,
    List,
} from '@shopify/polaris';

interface GenerateResponse {
    success: boolean;
    stats: {
        requested: number;
        created: number;
        failed: number;
    };
    errors?: string[];
}

interface DeleteResponse {
    success: boolean;
    stats: {
        deleted: number;
        failed: number;
    };
}

function ProductGeneratorContent() {
    const [count, setCount] = useState('50');
    const [productType, setProductType] = useState('Apparel');
    const [vendor, setVendor] = useState('Test Store');
    const [isGenerating, setIsGenerating] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [result, setResult] = useState<GenerateResponse | null>(null);
    const [deleteResult, setDeleteResult] = useState<DeleteResponse | null>(null);
    const [error, setError] = useState<string | null>(null);

    const handleGenerate = useCallback(async (e: FormEvent) => {
        e.preventDefault();
        setError(null);
        setResult(null);
        setDeleteResult(null);
        setIsGenerating(true);

        try {
            const response = await fetch(route('product-generator.generate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    count: parseInt(count),
                    product_type: productType,
                    vendor: vendor,
                }),
            });

            if (!response.ok) {
                throw new Error('Failed to generate products');
            }

            const data = await response.json();
            setResult(data);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setIsGenerating(false);
        }
    }, [count, productType, vendor]);

    const handleDelete = useCallback(async () => {
        if (!confirm('Are you sure you want to delete all test products? This cannot be undone.')) {
            return;
        }

        setError(null);
        setDeleteResult(null);
        setIsDeleting(true);

        try {
            const response = await fetch(route('product-generator.delete'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') || '',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to delete products');
            }

            const data = await response.json();
            setDeleteResult(data);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setIsDeleting(false);
        }
    }, []);

    return (
        <Page
            title="Bulk Product Generator"
            subtitle="Generate dummy products for testing the bulk tagger"
            backAction={{ content: 'Dashboard', url: '/dashboard' }}
        >
            <Layout>
                <Layout.Section>
                    <Banner title="Testing Tool" tone="info">
                        <p>
                            This tool creates test products in your Shopify store. All products
                            are tagged with "Test Data" for easy identification and cleanup.
                        </p>
                    </Banner>
                </Layout.Section>

                {error && (
                    <Layout.Section>
                        <Banner title="Error" tone="critical" onDismiss={() => setError(null)}>
                            <p>{error}</p>
                        </Banner>
                    </Layout.Section>
                )}

                {result && (
                    <Layout.Section>
                        <Banner title="Generation Complete!" tone="success" onDismiss={() => setResult(null)}>
                            <BlockStack gap="200">
                                <Text as="p" tone="success">
                                    <strong>{result.stats.created}</strong> products created successfully
                                </Text>
                                {result.stats.failed > 0 && (
                                    <Text as="p" tone="critical">
                                        <strong>{result.stats.failed}</strong> failed
                                    </Text>
                                )}
                                {result.errors && result.errors.length > 0 && (
                                    <BlockStack gap="100">
                                        <Text as="p" fontWeight="semibold">Errors:</Text>
                                        <List type="bullet">
                                            {result.errors.map((err, i) => (
                                                <List.Item key={i}>{err}</List.Item>
                                            ))}
                                        </List>
                                    </BlockStack>
                                )}
                            </BlockStack>
                        </Banner>
                    </Layout.Section>
                )}

                {deleteResult && (
                    <Layout.Section>
                        <Banner title="Cleanup Complete!" tone="success" onDismiss={() => setDeleteResult(null)}>
                            <BlockStack gap="200">
                                <Text as="p" tone="success">
                                    <strong>{deleteResult.stats.deleted}</strong> test products deleted
                                </Text>
                                {deleteResult.stats.failed > 0 && (
                                    <Text as="p" tone="critical">
                                        <strong>{deleteResult.stats.failed}</strong> failed to delete
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
                                Generate Products
                            </Text>
                            <Text as="p" tone="subdued">
                                Create dummy products with random names, colors, and materials
                            </Text>

                            <form onSubmit={handleGenerate}>
                                <FormLayout>
                                    <FormLayout.Group condensed>
                                        <TextField
                                            label="Number of Products"
                                            type="number"
                                            value={count}
                                            onChange={setCount}
                                            min={1}
                                            max={250}
                                            helpText="Max: 250 products"
                                            autoComplete="off"
                                        />

                                        <TextField
                                            label="Product Type"
                                            value={productType}
                                            onChange={setProductType}
                                            placeholder="e.g., Apparel"
                                            autoComplete="off"
                                        />

                                        <TextField
                                            label="Vendor"
                                            value={vendor}
                                            onChange={setVendor}
                                            placeholder="e.g., Test Store"
                                            autoComplete="off"
                                        />
                                    </FormLayout.Group>

                                    <InlineStack gap="300">
                                        <Button
                                            submit
                                            variant="primary"
                                            loading={isGenerating}
                                            disabled={isDeleting}
                                        >
                                            Generate Products
                                        </Button>

                                        <Button
                                            tone="critical"
                                            onClick={handleDelete}
                                            loading={isDeleting}
                                            disabled={isGenerating}
                                        >
                                            Delete All Test Products
                                        </Button>
                                    </InlineStack>

                                    {isGenerating && (
                                        <Banner title="Generating..." tone="info">
                                            <p>
                                                This may take a few minutes depending on the number of
                                                products. Please wait...
                                            </p>
                                        </Banner>
                                    )}

                                    {isDeleting && (
                                        <Banner title="Deleting..." tone="info">
                                            <p>Removing all test products. Please wait...</p>
                                        </Banner>
                                    )}
                                </FormLayout>
                            </form>
                        </BlockStack>
                    </Card>
                </Layout.Section>

                <Layout.Section>
                    <Card>
                        <BlockStack gap="400">
                            <Text as="h2" variant="headingMd">
                                What Gets Generated?
                            </Text>
                            <BlockStack gap="200">
                                <Text as="p">
                                    <strong>Product Names:</strong> Random combinations like "Red
                                    Cotton T-Shirt #1", "Blue Leather Jacket #2"
                                </Text>
                                <Text as="p">
                                    <strong>Types:</strong> T-Shirt, Hoodie, Jacket, Pants, Shoes,
                                    Hat, Bag, Accessory
                                </Text>
                                <Text as="p">
                                    <strong>Colors:</strong> Red, Blue, Green, Black, White, Yellow,
                                    Purple, Orange, Pink, Gray
                                </Text>
                                <Text as="p">
                                    <strong>Materials:</strong> Cotton, Polyester, Wool, Leather,
                                    Denim, Silk
                                </Text>
                                <Text as="p">
                                    <strong>Tags:</strong> Each product gets tagged with its type,
                                    color, material, and "Test Data"
                                </Text>
                            </BlockStack>
                        </BlockStack>
                    </Card>
                </Layout.Section>
            </Layout>
        </Page>
    );
}

export default function ProductGeneratorIndex() {
    return (
        <>
            <Head title="Product Generator" />
            <PolarisProvider>
                <ProductGeneratorContent />
            </PolarisProvider>
        </>
    );
}

// Simple layout without authentication requirement
ProductGeneratorIndex.layout = (page: React.ReactNode) => (
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
                © 2025 Shopify Bulk Product Generator | Built with Laravel, React & Polaris
            </p>
        </footer>
    </div>
);
