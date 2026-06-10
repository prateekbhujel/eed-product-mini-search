import React from 'react';
import { createRoot } from 'react-dom/client';
import CatalogApp from './modules/catalog/CatalogApp.jsx';
import './bootstrap';

createRoot(document.getElementById('eed-root')).render(
    <React.StrictMode>
        <CatalogApp />
    </React.StrictMode>,
);
