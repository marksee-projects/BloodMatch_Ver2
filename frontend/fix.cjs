const fs = require('fs');

function fixRequests() {
  let req = fs.readFileSync('src/pages/RequestsPage.jsx', 'utf8');
  
  // Replace the load callback and useEffect
  const loadRegex = /  const load = useCallback\(\(\) => \{\s+return api\s+\.get\('\/api\/my\/requests'\)\s+\.then\(\(data\) => setRequests\(data\.requests \|\| \[\]\)\)\s+\.catch\(\(err\) => setErrorAlert\(err\.message\)\)\s+\}, \[\]\)\s+useEffect\(\(\) => \{\s+load\(\)\s+\}, \[load\]\)/;
  
  req = req.replace(loadRegex, `  const { data: requests, isLoading, refetch } = useQuery({
    queryKey: ['my-requests'],
    queryFn: async () => {
      const data = await api.get('/api/my/requests');
      return data.requests || [];
    }
  });

  const load = () => refetch();`);

  fs.writeFileSync('src/pages/RequestsPage.jsx', req);
}

function fixProfile() {
  let prof = fs.readFileSync('src/pages/ProfilePage.jsx', 'utf8');
  
  // Replace imports
  prof = prof.replace(/import React, \{ useEffect, useState \} from 'react'/, 
    "import React, { useState, useEffect } from 'react'\nimport { useQuery } from '@tanstack/react-query'");
  
  // Remove profile and reports state
  prof = prof.replace(/const \[profile, setProfile\] = useState\(null\)\s+const \[form, setForm\] = useState\(null\)/, 
    "const [form, setForm] = useState(null)");
  prof = prof.replace(/const \[reports, setReports\] = useState\(\[\]\)/, "");
  
  // Replace load function and useEffect
  const loadRegex = /const load = \(\) =>\s+api\s+\.get\('\/api\/profile'\)\s+\.then\(\(data\) => \{\s+setProfile\(data\.profile\)\s+setForm\(\{[^]*?\}\)\s+const loc = data\.profile\.location\s+setLocation\(\{[^]*?\}\)\s+if \(data\.profile\.role === 'member'\) \{\s+return api\.get\('\/api\/my\/donation-reports'\)\.then\(\(d\) => setReports\(\d\.reports \|\| \[\]\)\)\s+\}\s+return null\s+\}\)\s+\.catch\(\(err\) => setErrorAlert\(\err\.message\)\)\s+useEffect\(\(\) => \{\s+load\(\)\s+\}, \[\]\)/;
  
  const newLoad = `  const { data, isLoading, refetch } = useQuery({
    queryKey: ['profile'],
    queryFn: async () => {
      const p = await api.get('/api/profile');
      let r = [];
      if (p.profile.role === 'member') {
        const d = await api.get('/api/my/donation-reports');
        r = d.reports || [];
      }
      return { profile: p.profile, reports: r };
    }
  });

  const profile = data?.profile;
  const reports = data?.reports || [];

  useEffect(() => {
    if (profile && !form) {
      setForm({
        full_name: profile.full_name,
        phone: profile.phone || '',
        date_of_birth: profile.date_of_birth || '',
        blood_type: profile.blood_type || ''
      });
      setLocation({
        location_id: profile.location?.location_id ?? null,
        municipality_code: profile.location?.municipality_code ?? null,
        barangay_code: profile.location?.barangay_code ?? null
      });
    }
  }, [profile]);

  const load = () => refetch();`;

  prof = prof.replace(loadRegex, newLoad);
  
  // Replace loading spinner check
  prof = prof.replace(/if \(!profile \|\| !form\) \{/, "if ((isLoading && !profile) || !form) {");
  
  // Replace setProfile updates
  prof = prof.replace(/setProfile\(data\.profile\)/g, "refetch()");
  
  fs.writeFileSync('src/pages/ProfilePage.jsx', prof);
}

fixRequests();
fixProfile();
console.log('Fixed pages successfully.');
