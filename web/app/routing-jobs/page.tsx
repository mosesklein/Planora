"use client";

import { useEffect, useMemo, useState } from "react";

const API_BASE = `${
  (process.env.NEXT_PUBLIC_API_BASE_URL || "http://localhost:8000").replace(/\/$/, "")
}/api`;

type HealthStatus = {
  status: string;
  db: boolean;
  redis: boolean;
  osrm: boolean;
};

type RoutingJob = {
  id: string;
  status: string;
  original_filename?: string;
  stored_path?: string;
  output_json_path?: string | null;
  output_csv_path?: string | null;
  error_message?: string | null;
  created_at?: string;
  updated_at?: string;
};

const formatDate = (value?: string) => {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
};

const buildErrorMessage = (data: unknown, fallback: string) => {
  if (data && typeof data === "object") {
    const message = (data as { message?: string }).message;
    const errors = (data as { errors?: Record<string, string[]> }).errors;

    if (message) return message;
    if (errors) {
      return Object.entries(errors)
        .map(([field, messages]) => `${field}: ${messages.join("; ")}`)
        .join(" | ");
    }
  }

  return fallback;
};

export default function RoutingJobsPage() {
  const [health, setHealth] = useState<HealthStatus | null>(null);
  const [healthLoading, setHealthLoading] = useState(false);
  const [healthError, setHealthError] = useState<string | null>(null);

  const [jobs, setJobs] = useState<RoutingJob[]>([]);
  const [jobsLoading, setJobsLoading] = useState(false);
  const [jobsError, setJobsError] = useState<string | null>(null);

  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  const [jobDetails, setJobDetails] = useState<Record<string, RoutingJob>>({});
  const [rowErrors, setRowErrors] = useState<Record<string, string>>({});
  const [processingJobId, setProcessingJobId] = useState<string | null>(null);
  const [viewingJobId, setViewingJobId] = useState<string | null>(null);

  useEffect(() => {
    fetchHealth();
    fetchJobs();
  }, []);

  const sortedJobs = useMemo(() => {
    return [...jobs].sort((a, b) => {
      const aTime = a.updated_at ? new Date(a.updated_at).getTime() : 0;
      const bTime = b.updated_at ? new Date(b.updated_at).getTime() : 0;
      return bTime - aTime;
    });
  }, [jobs]);

  const fetchHealth = async () => {
    setHealthLoading(true);
    setHealthError(null);
    try {
      const response = await fetch(`${API_BASE}/health`);
      if (!response.ok) {
        const data = await response.json().catch(() => null);
        throw new Error(buildErrorMessage(data, "Health check failed."));
      }
      const data = (await response.json()) as HealthStatus;
      setHealth(data);
    } catch (error) {
      setHealth(null);
      setHealthError(
        error instanceof Error ? error.message : "Unable to load health status."
      );
    } finally {
      setHealthLoading(false);
    }
  };

  const fetchJobs = async () => {
    setJobsLoading(true);
    setJobsError(null);
    try {
      const response = await fetch(`${API_BASE}/routing-jobs`);
      if (!response.ok) {
        const data = await response.json().catch(() => null);
        throw new Error(buildErrorMessage(data, "Failed to load jobs."));
      }
      const data = (await response.json()) as RoutingJob[];
      setJobs(data);
    } catch (error) {
      setJobsError(error instanceof Error ? error.message : "Failed to load jobs.");
    } finally {
      setJobsLoading(false);
    }
  };

  const handleUpload = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setUploadError(null);

    if (!selectedFile) {
      setUploadError("Please choose a CSV file to upload.");
      return;
    }

    const formData = new FormData();
    formData.append("file", selectedFile);

    setUploading(true);
    try {
      const response = await fetch(`${API_BASE}/routing-jobs`, {
        method: "POST",
        body: formData,
      });

      const data = await response.json().catch(() => null);

      if (!response.ok) {
        throw new Error(buildErrorMessage(data, "Upload failed."));
      }

      setSelectedFile(null);
      await fetchJobs();
    } catch (error) {
      setUploadError(error instanceof Error ? error.message : "Upload failed.");
    } finally {
      setUploading(false);
    }
  };

  const handleView = async (id: string) => {
    setViewingJobId(id);
    setRowErrors((prev) => ({ ...prev, [id]: "" }));

    try {
      const response = await fetch(`${API_BASE}/routing-jobs/${id}`);
      const data = await response.json().catch(() => null);

      if (!response.ok || !data) {
        throw new Error(buildErrorMessage(data, "Failed to load job."));
      }

      setJobDetails((prev) => ({ ...prev, [id]: data as RoutingJob }));
    } catch (error) {
      setRowErrors((prev) => ({
        ...prev,
        [id]: error instanceof Error ? error.message : "Failed to load job.",
      }));
    } finally {
      setViewingJobId(null);
    }
  };

  const handleProcess = async (id: string) => {
    setProcessingJobId(id);
    setRowErrors((prev) => ({ ...prev, [id]: "" }));

    try {
      const response = await fetch(`${API_BASE}/routing-jobs/${id}/process`, {
        method: "POST",
      });
      const data = await response.json().catch(() => null);

      if (!response.ok || !data) {
        throw new Error(buildErrorMessage(data, "Processing failed."));
      }

      setJobDetails((prev) => ({ ...prev, [id]: data as RoutingJob }));
      await fetchJobs();
    } catch (error) {
      setRowErrors((prev) => ({
        ...prev,
        [id]: error instanceof Error ? error.message : "Processing failed.",
      }));
    } finally {
      setProcessingJobId(null);
    }
  };

  return (
    <main className="mx-auto flex min-h-screen max-w-5xl flex-col gap-10 px-6 py-10 text-sm sm:text-base">
      <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Tools</p>
          <h1 className="text-2xl font-semibold text-gray-900">Routing Jobs</h1>
          <p className="text-gray-600">
            Test the routing API locally by uploading CSV files and processing them synchronously.
          </p>
        </div>
        <button
          type="button"
          onClick={() => {
            fetchHealth();
            fetchJobs();
          }}
          className="self-start rounded border border-gray-300 px-3 py-2 text-sm font-medium text-gray-800 shadow-sm transition hover:bg-gray-100"
        >
          Refresh
        </button>
      </header>

      <section className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">Health</h2>
          {healthLoading && <span className="text-xs text-gray-500">Loading…</span>}
        </div>
        {healthError && <p className="mt-2 text-sm text-red-600">{healthError}</p>}
        {health && (
          <dl className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
            <div className="rounded border border-gray-100 bg-gray-50 px-3 py-2">
              <dt className="text-xs uppercase tracking-wide text-gray-500">Status</dt>
              <dd className="text-sm font-medium text-gray-900">{health.status}</dd>
            </div>
            <div className="rounded border border-gray-100 bg-gray-50 px-3 py-2">
              <dt className="text-xs uppercase tracking-wide text-gray-500">Database</dt>
              <dd className="text-sm font-medium text-gray-900">{String(health.db)}</dd>
            </div>
            <div className="rounded border border-gray-100 bg-gray-50 px-3 py-2">
              <dt className="text-xs uppercase tracking-wide text-gray-500">Redis</dt>
              <dd className="text-sm font-medium text-gray-900">{String(health.redis)}</dd>
            </div>
            <div className="rounded border border-gray-100 bg-gray-50 px-3 py-2">
              <dt className="text-xs uppercase tracking-wide text-gray-500">OSRM</dt>
              <dd className="text-sm font-medium text-gray-900">{String(health.osrm)}</dd>
            </div>
          </dl>
        )}
      </section>

      <section className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h2 className="text-lg font-semibold text-gray-900">Upload CSV</h2>
        <p className="text-sm text-gray-600">
          Upload a CSV with <code className="rounded bg-gray-100 px-1 py-0.5">id,lat,lng</code> columns to create a routing job.
        </p>
        <form className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center" onSubmit={handleUpload}>
          <input
            type="file"
            name="file"
            accept=".csv,text/csv"
            onChange={(event) => setSelectedFile(event.target.files?.[0] ?? null)}
            className="text-sm text-gray-800"
          />
          <button
            type="submit"
            disabled={uploading}
            className="inline-flex w-fit items-center justify-center rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
          >
            {uploading ? "Uploading…" : "Upload"}
          </button>
        </form>
        {selectedFile && (
          <p className="mt-1 text-xs text-gray-500">Selected: {selectedFile.name}</p>
        )}
        {uploadError && <p className="mt-2 text-sm text-red-600">{uploadError}</p>}
      </section>

      <section className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">Jobs</h2>
          {jobsLoading && <span className="text-xs text-gray-500">Loading…</span>}
        </div>
        {jobsError && <p className="mt-2 text-sm text-red-600">{jobsError}</p>}
        <div className="mt-3 overflow-x-auto">
          <table className="min-w-full text-left text-sm text-gray-800">
            <thead>
              <tr className="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                <th className="px-2 py-2">ID</th>
                <th className="px-2 py-2">Filename</th>
                <th className="px-2 py-2">Status</th>
                <th className="px-2 py-2">Updated</th>
                <th className="px-2 py-2 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {sortedJobs.length === 0 && (
                <tr>
                  <td className="px-2 py-3 text-sm text-gray-500" colSpan={5}>
                    No routing jobs yet.
                  </td>
                </tr>
              )}
              {sortedJobs.map((job) => {
                const detail = jobDetails[job.id];
                const error = rowErrors[job.id];
                const isProcessing = processingJobId === job.id;
                const isViewing = viewingJobId === job.id;

                return (
                  <tr key={job.id} className="border-b border-gray-100 align-top">
                    <td className="px-2 py-3 font-mono text-[13px] text-gray-900">{job.id}</td>
                    <td className="px-2 py-3 text-gray-800">{job.original_filename ?? "-"}</td>
                    <td className="px-2 py-3">
                      <span className="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800">
                        {job.status}
                      </span>
                    </td>
                    <td className="px-2 py-3 text-gray-700">{formatDate(job.updated_at)}</td>
                    <td className="px-2 py-3 text-right">
                      <div className="flex justify-end gap-2">
                        <button
                          type="button"
                          disabled={isViewing}
                          onClick={() => handleView(job.id)}
                          className="rounded border border-gray-300 px-3 py-1 text-xs font-medium text-gray-800 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:bg-gray-50"
                        >
                          {isViewing ? "Loading…" : "View"}
                        </button>
                        <button
                          type="button"
                          disabled={isProcessing}
                          onClick={() => handleProcess(job.id)}
                          className="rounded bg-indigo-600 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-indigo-300"
                        >
                          {isProcessing ? "Processing…" : "Process"}
                        </button>
                      </div>
                      {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
                      {detail && (
                        <pre className="mt-2 overflow-x-auto whitespace-pre-wrap rounded bg-gray-50 p-3 text-xs text-gray-800">
                          {JSON.stringify(detail, null, 2)}
                        </pre>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </section>
    </main>
  );
}
