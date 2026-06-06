'use client';

import { useState } from 'react';
import { apiGet, apiPostFormData, apiPostJson } from '../utils/fetch';

type Result = { data: unknown; error?: string } | null;

export default function Home() {
  const [getResult, setGetResult] = useState<Result>(null);
  const [formDataResult, setFormDataResult] = useState<Result>(null);
  const [jsonResult, setJsonResult] = useState<Result>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  const handleGet = async () => {
    try {
      const res = await apiGet({ greeting: 'Hello', name: 'World' });
      setGetResult({ data: await res.json() });
    } catch (e) {
      setGetResult({ data: null, error: String(e) });
    }
  };

  const handlePostFormData = async () => {
    try {
      const res = await apiPostFormData(selectedFile
        ? { file: selectedFile, name: selectedFile.name }
        : { label: 'demo-upload' });
      setFormDataResult({ data: await res.json() });
    } catch (e) {
      setFormDataResult({ data: null, error: String(e) });
    }
  };

  const handlePostJson = async () => {
    try {
      const res = await apiPostJson({ message: 'Hello from TypeScript', count: 42 });
      setJsonResult({ data: await res.json() });
    } catch (e) {
      setJsonResult({ data: null, error: String(e) });
    }
  };

  return (
    <main>
      <h1>
        <code>ts-php-type-bridge</code>
      </h1>
      <p className="subtitle">
        TypeScript types → AST → JSON → PHP-Parser → phpdoc → PHPStan
      </p>

      <div className="card">
        <h2>
          <span className="badge badge-get">GET</span>
          /api/hello.php
        </h2>
        <p style={{ fontSize: '0.85rem', marginBottom: '0.75rem', color: '#555' }}>
          Params typed as <code>GetParam = &apos;greeting&apos; | &apos;name&apos;</code>
        </p>
        <button onClick={handleGet}>Send GET</button>
        {getResult?.error && <p className="error">{getResult.error}</p>}
        {getResult?.data !== null && getResult?.data !== undefined && (
          <pre>{JSON.stringify(getResult.data, null, 2)}</pre>
        )}
      </div>

      <div className="card">
        <h2>
          <span className="badge badge-post">POST</span>
          /api/upload.php — FormData
        </h2>
        <p style={{ fontSize: '0.85rem', marginBottom: '0.75rem', color: '#555' }}>
          Keys typed as <code>PostFormDataKey = &apos;file&apos; | &apos;label&apos;</code>
        </p>
        <input
          type="file"
          onChange={(e) => setSelectedFile(e.target.files?.[0] ?? null)}
          style={{ display: 'block', marginBottom: '0.5rem' }}
        />
        <button onClick={handlePostFormData}>Send POST FormData</button>
        {formDataResult?.error && <p className="error">{formDataResult.error}</p>}
        {formDataResult?.data !== null && formDataResult?.data !== undefined && (
          <pre>{JSON.stringify(formDataResult.data, null, 2)}</pre>
        )}
      </div>

      <div className="card">
        <h2>
          <span className="badge badge-post">POST</span>
          /api/data.php — JSON
        </h2>
        <p style={{ fontSize: '0.85rem', marginBottom: '0.75rem', color: '#555' }}>
          Keys typed as <code>PostJsonKey = &apos;message&apos; | &apos;count&apos;</code>
        </p>
        <button onClick={handlePostJson}>Send POST JSON</button>
        {jsonResult?.error && <p className="error">{jsonResult.error}</p>}
        {jsonResult?.data !== null && jsonResult?.data !== undefined && (
          <pre>{JSON.stringify(jsonResult.data, null, 2)}</pre>
        )}
      </div>
    </main>
  );
}
