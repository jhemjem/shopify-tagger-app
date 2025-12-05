import { AppProvider } from '@shopify/polaris';
import enTranslations from '@shopify/polaris/locales/en.json';
import { ReactNode } from 'react';

interface PolarisProviderProps {
    children: ReactNode;
}

export function PolarisProvider({ children }: PolarisProviderProps) {
    return (
        <AppProvider i18n={enTranslations}>
            {children}
        </AppProvider>
    );
}
