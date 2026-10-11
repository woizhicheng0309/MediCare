import { Fragment, useState } from 'react';
import { Alert, Box, Button, Chip, MenuItem, Paper, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, TableSortLabel, TextField, Typography } from '@mui/material';
import type { Visit } from '../types';
const money = (amount: number) => `TWD ${amount.toLocaleString('en-US', { maximumFractionDigits: 0 })}`;

type SortField = 'employee' | 'company' | 'date' | 'clinic' | 'amount' | 'status' | 'doctor' | 'doctorTitle' | 'diagnosis' | 'leaveStart' | 'leaveEnd' | 'medications';
const sortFields: { field: SortField; label: string }[] = [
  { field: 'employee', label: 'Employee' }, { field: 'company', label: 'Company' },
  { field: 'date', label: 'Visit date / time' }, { field: 'clinic', label: 'Clinic' },
  { field: 'amount', label: 'Covered amount' }, { field: 'status', label: 'Status' },
  { field: 'doctor', label: 'Doctor name' }, { field: 'doctorTitle', label: 'Doctor title' },
  { field: 'diagnosis', label: 'Diagnosis' }, { field: 'leaveStart', label: 'Sick leave start' },
  { field: 'leaveEnd', label: 'Sick leave end' }, { field: 'medications', label: 'Medication names' },
];

// Compare raw amounts and date strings, rather than formatted currency or local dates.
function sortValue(visit: Visit, field: SortField): string | number | null {
  switch (field) {
    case 'employee': return visit.user?.name ?? null;
    case 'company': return visit.user?.company?.name ?? null;
    case 'date': return `${visit.visited_on} ${visit.visited_time?.slice(0, 5) ?? ''}`;
    case 'clinic': return visit.clinic;
    case 'amount': return visit.covered_amount_cents;
    case 'status': return visit.status;
    case 'doctor': return visit.doctor_name;
    case 'doctorTitle': return visit.doctor_title;
    case 'diagnosis': return visit.diagnosis;
    case 'leaveStart': return visit.sick_leave_started_on;
    case 'leaveEnd': return visit.sick_leave_ended_on;
    case 'medications': return visit.medications?.map((item) => item.name).sort().join(', ') || null;
  }
}

export default function VisitTable({ visits, includeEmployee = false, onDecision, busy = false }: { visits: Visit[]; includeEmployee?: boolean; onDecision?: (visit: Visit, status: 'approved' | 'rejected') => void; busy?: boolean }) {
  const [sortField, setSortField] = useState<SortField>('date');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
  if (!visits.length) return <Alert severity="info">No visits to display.</Alert>;
  const columns = 4 + Number(includeEmployee) + Number(Boolean(onDecision));
  const mobileLabel = (label: string) => <Typography variant="caption" color="text.secondary" sx={{ display: { xs: 'block', sm: 'none' } }}>{label}</Typography>;

  function changeSort(field: SortField) {
    setSortDirection(sortField === field && sortDirection === 'asc' ? 'desc' : 'asc');
    setSortField(field);
  }
  // Sort a copy so API data stays untouched. Missing values stay last in both directions.
  const sortedVisits = [...visits].sort((first, second) => {
    const a = sortValue(first, sortField), b = sortValue(second, sortField);
    if (a == null || a === '') return b == null || b === '' ? first.id - second.id : 1;
    if (b == null || b === '') return -1;
    const comparison = typeof a === 'number' && typeof b === 'number' ? a - b : String(a).localeCompare(String(b), 'en', { numeric: true, sensitivity: 'base' });
    return comparison === 0 ? first.id - second.id : comparison * (sortDirection === 'asc' ? 1 : -1);
  });
  const sortLabel = (field: SortField, label: string) => <TableSortLabel component="button" type="button" active={sortField === field} direction={sortField === field ? sortDirection : 'asc'} onClick={() => changeSort(field)} sx={{ border: 0, p: 0, bgcolor: 'transparent', font: 'inherit', textAlign: 'left' }}>{label}</TableSortLabel>;
  const sortHeader = (field: SortField, label: string) => <TableCell sortDirection={sortField === field ? sortDirection : false}>{sortLabel(field, label)}</TableCell>;

  // Keep review actions beside the visit summary; medical details use a second row.
  return <Stack spacing={2}>
    {/* The selector also exposes detail fields and sorting on mobile screens. */}
    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
      <TextField select size="small" label="Sort records by" value={sortField} onChange={(event) => { setSortField(event.target.value as SortField); setSortDirection('asc'); }} sx={{ minWidth: 200 }}>
        {sortFields.filter(({ field }) => includeEmployee || (field !== 'employee' && field !== 'company')).map(({ field, label }) => <MenuItem key={field} value={field}>{label}</MenuItem>)}
      </TextField>
      <Button variant="outlined" onClick={() => setSortDirection((direction) => direction === 'asc' ? 'desc' : 'asc')} aria-label={`Sort direction: ${sortDirection === 'asc' ? 'ascending' : 'descending'}`}>{sortDirection === 'asc' ? 'Ascending ↑' : 'Descending ↓'}</Button>
    </Stack>
    <TableContainer component={Paper} variant="outlined">
    <Table aria-label="Clinic visits" sx={{ display: { xs: 'block', sm: 'table' }, '& .MuiTableCell-root': { px: { xs: 2, sm: 1.5 }, overflowWrap: 'anywhere' } }}>
      <TableHead sx={{ display: { xs: 'none', sm: 'table-header-group' } }}>
        <TableRow>
          {includeEmployee && sortHeader('employee', 'Employee / company')}
          {sortHeader('date', 'Visit date')}{sortHeader('clinic', 'Clinic')}{sortHeader('amount', 'Covered amount')}{sortHeader('status', 'Status')}
          {onDecision && <TableCell sx={{ width: 176 }}>Review</TableCell>}
        </TableRow>
      </TableHead>
      <TableBody sx={{ display: { xs: 'block', sm: 'table-row-group' } }}>
        {sortedVisits.map((visit) => <Fragment key={visit.id}>
          {/* Small screens show labeled fields in a card instead of a wide table. */}
          <TableRow sx={{ display: { xs: 'grid', sm: 'table-row' }, gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1fr)', '& > td': { borderBottom: 0, display: { xs: 'block', sm: 'table-cell' }, py: 1.5 } }}>
            {includeEmployee && <TableCell>{mobileLabel('Employee / company')}{visit.user?.name ?? 'Unassigned'}<Typography variant="caption" sx={{ display: 'block' }} color="text.secondary">{visit.user?.company?.name ?? 'No company'}</Typography></TableCell>}
            <TableCell sx={{ whiteSpace: 'nowrap' }}>{mobileLabel('Visit date')}{visit.visited_on}
              {/* MySQL may include seconds; display clinic local time as HH:MM. */}
              <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>{visit.visited_time ? visit.visited_time.slice(0, 5) : 'Time not recorded'}</Typography>
            </TableCell>
            <TableCell>{mobileLabel('Clinic')}{visit.clinic}</TableCell>
            <TableCell sx={{ whiteSpace: 'nowrap' }}>{mobileLabel('Covered amount')}{money(visit.covered_amount_cents / 100)}</TableCell>
            <TableCell>{mobileLabel('Status')}<Chip size="small" label={visit.status} color={visit.status === 'approved' ? 'success' : visit.status === 'rejected' ? 'error' : 'warning'} /></TableCell>
            {onDecision && <TableCell sx={{ gridColumn: '1 / -1' }}>
              {visit.user ? <Stack direction="row" spacing={1}>
                <Button size="small" variant="contained" color="success" disabled={busy} onClick={() => onDecision(visit, 'approved')}>Approve</Button>
                <Button size="small" variant="outlined" color="error" sx={{ '&:not(.Mui-disabled)': { color: 'error.main', borderColor: 'error.main' }, '&:hover': { bgcolor: 'error.light' } }} disabled={busy} onClick={() => onDecision(visit, 'rejected')}>Reject</Button>
              </Stack> : <Typography variant="caption" color="text.secondary">Employee assignment required</Typography>}
            </TableCell>}
          </TableRow>
          <TableRow sx={{ display: { xs: 'block', sm: 'table-row' }, bgcolor: 'action.hover' }}>
            <TableCell colSpan={columns} sx={{ display: { xs: 'block', sm: 'table-cell' }, py: 1.5 }}>
              <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'repeat(2, minmax(0, 1fr))' }, gap: 2 }}>
                <Box><Typography variant="caption" color="text.secondary">{sortLabel('doctor', 'Doctor')}</Typography><Typography variant="body2">{visit.doctor_name || '—'}</Typography>{visit.doctor_title && <Typography variant="body2" color="text.secondary">{visit.doctor_title}</Typography>}</Box>
                <Box><Typography variant="caption" color="text.secondary">{sortLabel('diagnosis', 'Diagnosis')}</Typography><Typography variant="body2">{visit.diagnosis || '—'}</Typography></Box>
                {/* Render date-only API strings without converting timezones. */}
                <Box><Typography variant="caption" color="text.secondary">{sortLabel('leaveStart', 'Sick leave')}</Typography><Typography variant="body2">{visit.sick_leave_started_on && visit.sick_leave_ended_on ? <><Box component="span" sx={{ whiteSpace: 'nowrap' }}>{visit.sick_leave_started_on}</Box>{' – '}<Box component="span" sx={{ whiteSpace: 'nowrap' }}>{visit.sick_leave_ended_on}</Box></> : '—'}</Typography></Box>
                <Box><Typography variant="caption" color="text.secondary">{sortLabel('medications', 'Medications')}</Typography>{visit.medications?.length ? visit.medications.map((medication) => <Typography variant="body2" key={medication.id}>{medication.name} · {money(medication.covered_amount_cents / 100)}{medication.dosage && ` · ${medication.dosage}`}{medication.instructions && ` · ${medication.instructions}`}</Typography>) : <Typography variant="body2">—</Typography>}</Box>
              </Box>
            </TableCell>
          </TableRow>
        </Fragment>)}
      </TableBody>
    </Table>
  </TableContainer>
  </Stack>;
}
