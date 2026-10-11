import { Fragment } from 'react';
import { Alert, Box, Button, Chip, Paper, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Typography } from '@mui/material';
import type { Visit } from '../types';
const money = (amount: number) => `TWD ${amount.toLocaleString('en-US', { maximumFractionDigits: 0 })}`;

export default function VisitTable({ visits, includeEmployee = false, onDecision, busy = false }: { visits: Visit[]; includeEmployee?: boolean; onDecision?: (visit: Visit, status: 'approved' | 'rejected') => void; busy?: boolean }) {
  if (!visits.length) return <Alert severity="info">No visits to display.</Alert>;
  const columns = 4 + Number(includeEmployee) + Number(Boolean(onDecision));
  const mobileLabel = (label: string) => <Typography variant="caption" color="text.secondary" sx={{ display: { xs: 'block', sm: 'none' } }}>{label}</Typography>;

  // Keep review actions beside the visit summary; medical details use a second row.
  return <TableContainer component={Paper} variant="outlined">
    <Table aria-label="Clinic visits" sx={{ display: { xs: 'block', sm: 'table' }, '& .MuiTableCell-root': { px: { xs: 2, sm: 1.5 }, overflowWrap: 'anywhere' } }}>
      <TableHead sx={{ display: { xs: 'none', sm: 'table-header-group' } }}>
        <TableRow>
          {includeEmployee && <TableCell>Employee / company</TableCell>}
          <TableCell>Visit date</TableCell><TableCell>Clinic</TableCell><TableCell>Covered amount</TableCell><TableCell>Status</TableCell>
          {onDecision && <TableCell sx={{ width: 176 }}>Review</TableCell>}
        </TableRow>
      </TableHead>
      <TableBody sx={{ display: { xs: 'block', sm: 'table-row-group' } }}>
        {visits.map((visit) => <Fragment key={visit.id}>
          {/* Small screens show labeled fields in a card instead of a wide table. */}
          <TableRow sx={{ display: { xs: 'grid', sm: 'table-row' }, gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1fr)', '& > td': { borderBottom: 0, display: { xs: 'block', sm: 'table-cell' }, py: 1.5 } }}>
            {includeEmployee && <TableCell>{mobileLabel('Employee / company')}{visit.user?.name ?? 'Unassigned'}<Typography variant="caption" sx={{ display: 'block' }} color="text.secondary">{visit.user?.company?.name ?? 'No company'}</Typography></TableCell>}
            <TableCell sx={{ whiteSpace: 'nowrap' }}>{mobileLabel('Visit date')}{visit.visited_on}</TableCell>
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
              <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr)' }, gap: 2 }}>
                <Box><Typography variant="caption" color="text.secondary">Diagnosis</Typography><Typography variant="body2">{visit.diagnosis || '—'}</Typography></Box>
                {/* Render date-only API strings without converting timezones. */}
                <Box><Typography variant="caption" color="text.secondary">Sick leave</Typography><Typography variant="body2">{visit.sick_leave_started_on && visit.sick_leave_ended_on ? <><Box component="span" sx={{ whiteSpace: 'nowrap' }}>{visit.sick_leave_started_on}</Box>{' – '}<Box component="span" sx={{ whiteSpace: 'nowrap' }}>{visit.sick_leave_ended_on}</Box></> : '—'}</Typography></Box>
                <Box><Typography variant="caption" color="text.secondary">Medications</Typography>{visit.medications?.length ? visit.medications.map((medication) => <Typography variant="body2" key={medication.id}>{medication.name} · {money(medication.covered_amount_cents / 100)}{medication.dosage && ` · ${medication.dosage}`}{medication.instructions && ` · ${medication.instructions}`}</Typography>) : <Typography variant="body2">—</Typography>}</Box>
              </Box>
            </TableCell>
          </TableRow>
        </Fragment>)}
      </TableBody>
    </Table>
  </TableContainer>;
}
