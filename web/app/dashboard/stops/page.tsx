import type { Metadata } from "next";

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000";

export const metadata: Metadata = {
  title: "Stops",
};

type Stop = {
  id: number;
  name: string;
  lat: number;
  lng: number;
  company_id: number;
  created_at: string;
  updated_at: string;
};

async function getStops(): Promise<Stop[]> {
  const response = await fetch(`${API_BASE_URL}/api/v1/stops`, {
    cache: "no-store",
  });

  if (!response.ok) {
    throw new Error("Failed to load stops");
  }

  return response.json();
}

export default async function StopsPage() {
  const stops = await getStops();

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-6 px-6 py-10">
      <header className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-slate-500">Dashboard</p>
          <h1 className="text-3xl font-semibold text-slate-900">Stops</h1>
          <p className="text-sm text-slate-600">
            Listing all available stops from the Laravel API.
          </p>
        </div>
      </header>

      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
            <tr>
              <th scope="col" className="px-4 py-3">
                ID
              </th>
              <th scope="col" className="px-4 py-3">
                Name
              </th>
              <th scope="col" className="px-4 py-3">
                Latitude
              </th>
              <th scope="col" className="px-4 py-3">
                Longitude
              </th>
              <th scope="col" className="px-4 py-3">
                Company
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 bg-white">
            {stops.map((stop) => (
              <tr key={stop.id} className="hover:bg-slate-50">
                <td className="px-4 py-3 font-medium text-slate-900">{stop.id}</td>
                <td className="px-4 py-3 text-slate-800">{stop.name}</td>
                <td className="px-4 py-3 text-slate-700">{stop.lat}</td>
                <td className="px-4 py-3 text-slate-700">{stop.lng}</td>
                <td className="px-4 py-3 text-slate-700">{stop.company_id}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
