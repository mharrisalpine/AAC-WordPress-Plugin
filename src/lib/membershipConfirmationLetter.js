import { getFullName } from '@/lib/memberProfile';
import { getTierDisplayLabel } from '@/lib/membershipTiers';

const AAC_LOGO_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/light-header-logo.svg';
const PDF_POINTS_WIDTH = 612;
const PDF_POINTS_HEIGHT = 792;
const PAGE_MARGIN_X = 54;
const PAGE_MARGIN_TOP = 44;
const PAGE_MARGIN_BOTTOM = 50;
const BODY_FONT_SIZE = 12;
const LABEL_FONT_SIZE = 12;
const LINE_HEIGHT = 18;
const HEADER_IMAGE_HEIGHT = 52;
const HEADER_IMAGE_GAP = 16;
const MAX_TEXT_WIDTH = PDF_POINTS_WIDTH - PAGE_MARGIN_X * 2;
const REGULAR_TEXT_WIDTH_FACTOR = 0.52;
const BOLD_TEXT_WIDTH_FACTOR = 0.56;

const escapePdfText = (value) =>
  String(value || '')
    .replace(/\\/g, '\\\\')
    .replace(/\(/g, '\\(')
    .replace(/\)/g, '\\)');

const formatMoney = (amount) => {
  const numericAmount = Number(amount || 0);
  return `$${numericAmount.toLocaleString()}`;
};

const formatMembershipDate = (value) => {
  if (!value) {
    return 'Not scheduled';
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return 'Not scheduled';
  }

  return parsed.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const buildCityStateZip = (accountInfo = {}) => {
  const city = String(accountInfo.city || '').trim();
  const state = String(accountInfo.state || '').trim();
  const zip = String(accountInfo.zip || '').trim();

  const cityState = [city, state].filter(Boolean).join(', ');
  return [cityState, zip].filter(Boolean).join(' ');
};

const measureText = (text, { bold = false, fontSize = BODY_FONT_SIZE } = {}) =>
  String(text || '').length * fontSize * (bold ? BOLD_TEXT_WIDTH_FACTOR : REGULAR_TEXT_WIDTH_FACTOR);

const normalizeSegments = (segments) => {
  const merged = [];

  segments.forEach((segment) => {
    const text = String(segment?.text || '');
    if (!text) {
      return;
    }

    const bold = Boolean(segment?.bold);
    const lastSegment = merged[merged.length - 1];

    if (lastSegment && lastSegment.bold === bold) {
      lastSegment.text += text;
      return;
    }

    merged.push({ text, bold });
  });

  if (!merged.length) {
    return [{ text: '', bold: false }];
  }

  const lastSegment = merged[merged.length - 1];
  lastSegment.text = lastSegment.text.replace(/\s+$/, '');

  return merged.filter((segment) => segment.text !== '');
};

const wrapSegments = (segments, maxWidth, fontSize = BODY_FONT_SIZE) => {
  const tokens = [];

  segments.forEach((segment) => {
    const text = String(segment?.text || '');
    const bold = Boolean(segment?.bold);

    if (!text) {
      return;
    }

    const matches = text.match(/\S+\s*/g);
    if (matches) {
      matches.forEach((match) => {
        tokens.push({ text: match, bold });
      });
      return;
    }

    tokens.push({ text, bold });
  });

  if (!tokens.length) {
    return [[]];
  }

  const wrapped = [];
  let currentLine = [];
  let currentWidth = 0;

  tokens.forEach((token) => {
    const tokenWidth = measureText(token.text, { bold: token.bold, fontSize });

    if (currentLine.length && currentWidth + tokenWidth > maxWidth) {
      wrapped.push(normalizeSegments(currentLine));
      currentLine = [];
      currentWidth = 0;
    }

    currentLine.push(token);
    currentWidth += tokenWidth;
  });

  if (currentLine.length) {
    wrapped.push(normalizeSegments(currentLine));
  }

  return wrapped.length ? wrapped : [[{ text: '', bold: false }]];
};

const getLetterBlocks = (profile) => {
  const accountInfo = profile?.account_info || {};
  const profileInfo = profile?.profile_info || {};
  const benefitsInfo = profile?.benefits_info || {};
  const memberName = getFullName(accountInfo);
  const membershipLevel = getTierDisplayLabel(profileInfo.tier, 'Free');
  const dateLabel = accountInfo.auto_renew ? 'Renewal Date' : 'Expiration Date';
  const membershipDate = accountInfo.auto_renew
    ? profileInfo.renewal_date
    : (profileInfo.expiration_date || profileInfo.renewal_date);
  const rescueAmount = formatMoney(benefitsInfo.rescue_amount);
  const medicalAmount = formatMoney(benefitsInfo.medical_amount);

  const line = (segments, fontSize = BODY_FONT_SIZE) => ({
    type: 'line',
    fontSize,
    segments: Array.isArray(segments) ? segments : [{ text: segments, bold: false }],
  });

  return [
    line([{ text: memberName, bold: true }]),
    line([{ text: accountInfo.street || '', bold: true }]),
    line([{ text: buildCityStateZip(accountInfo), bold: true }]),
    { type: 'spacer', height: 6 },
    line([{ text: 'Membership ID: ' }, { text: profileInfo.member_id || 'N/A', bold: true }], LABEL_FONT_SIZE),
    line([{ text: 'Membership Level: ' }, { text: membershipLevel, bold: true }], LABEL_FONT_SIZE),
    line([{ text: `${dateLabel}: ` }, { text: formatMembershipDate(membershipDate), bold: true }], LABEL_FONT_SIZE),
    { type: 'spacer', height: 8 },
    line([{ text: 'To Whom it May Concern,' }]),
    { type: 'spacer', height: 4 },
    line([
      { text: 'This letter confirms that ' },
      { text: memberName, bold: true },
      { text: ' is a member of The American Alpine Club.' },
    ]),
    line([
      { text: membershipLevel, bold: true },
      { text: ' level members are entitled to ' },
      { text: rescueAmount, bold: true },
      { text: ' in rescue services, ' },
      { text: medicalAmount, bold: true },
      { text: ' in medical expense coverage, and $15,000 in mortal remains transportation coverage from Redpoint Travel Protection through the expiration of their membership.' },
    ]),
    { type: 'spacer', height: 4 },
    line([{ text: 'Proof of service through Redpoint Travel Protection can be verified with this letter for the above-mentioned membership level.' }]),
    { type: 'spacer', height: 4 },
    line([{ text: 'In case of rescue contact Redpoint Travel Protection: +01-628-251-1510' }]),
    { type: 'spacer', height: 4 },
    line([{ text: 'Please contact the American Alpine Club with any questions at 303-384-0110 or email us at info@americanalpineclub.org and visit americanalpineclub.org/rescue for more information.' }]),
    { type: 'spacer', height: 8 },
    line([{ text: 'Regards,' }]),
    { type: 'spacer', height: 2 },
    line([{ text: 'The American Alpine Club' }]),
    line([{ text: '710 Tenth Street Suite 100' }]),
    line([{ text: 'Golden, CO 80401 USA' }]),
  ];
};

const buildTextLines = (profile) => {
  const blocks = getLetterBlocks(profile);
  const lines = [];

  blocks.forEach((block) => {
    if (block.type === 'spacer') {
      lines.push({ type: 'spacer', height: block.height });
      return;
    }

    wrapSegments(block.segments, MAX_TEXT_WIDTH, block.fontSize).forEach((wrappedSegments) => {
      lines.push({
        type: 'text',
        fontSize: block.fontSize,
        segments: wrappedSegments,
      });
    });
  });

  return lines;
};

const loadSvgImage = (svgText) =>
  new Promise((resolve, reject) => {
    const blob = new Blob([svgText], { type: 'image/svg+xml' });
    const blobUrl = URL.createObjectURL(blob);
    const image = new Image();

    image.onload = () => {
      URL.revokeObjectURL(blobUrl);
      resolve(image);
    };

    image.onerror = (error) => {
      URL.revokeObjectURL(blobUrl);
      reject(error);
    };

    image.src = blobUrl;
  });

const buildHeaderImage = async () => {
  try {
    const response = await fetch(AAC_LOGO_URL);
    if (!response.ok) {
      return null;
    }

    const svgText = await response.text();
    const logoImage = await loadSvgImage(svgText);
    const canvas = document.createElement('canvas');
    const canvasWidth = 1800;
    const canvasHeight = 240;
    const paddingX = 72;
    const paddingY = 42;
    const availableWidth = canvasWidth - paddingX * 2;
    const availableHeight = canvasHeight - paddingY * 2;
    const logoScale = Math.min(availableWidth / logoImage.width, availableHeight / logoImage.height);
    const drawWidth = logoImage.width * logoScale;
    const drawHeight = logoImage.height * logoScale;
    const ctx = canvas.getContext('2d');

    if (!ctx) {
      return null;
    }

    canvas.width = canvasWidth;
    canvas.height = canvasHeight;

    ctx.fillStyle = '#120604';
    ctx.fillRect(0, 0, canvasWidth, canvasHeight);
    ctx.drawImage(logoImage, paddingX, (canvasHeight - drawHeight) / 2, drawWidth, drawHeight);

    return {
      dataUrl: canvas.toDataURL('image/jpeg', 0.92),
      pixelWidth: canvasWidth,
      pixelHeight: canvasHeight,
    };
  } catch (_error) {
    return null;
  }
};

const buildPageStream = (profile, headerImageObjectName) => {
  const lines = buildTextLines(profile);
  const imageWidth = MAX_TEXT_WIDTH;
  const imageY = PDF_POINTS_HEIGHT - PAGE_MARGIN_TOP - HEADER_IMAGE_HEIGHT;
  let y = PDF_POINTS_HEIGHT - PAGE_MARGIN_TOP - HEADER_IMAGE_HEIGHT - HEADER_IMAGE_GAP;
  const content = [];

  if (headerImageObjectName) {
    content.push('q');
    content.push(`${imageWidth} 0 0 ${HEADER_IMAGE_HEIGHT} ${PAGE_MARGIN_X} ${imageY} cm`);
    content.push(`/${headerImageObjectName} Do`);
    content.push('Q');
  }

  lines.forEach((line) => {
    if (line.type === 'spacer') {
      y -= line.height;
      return;
    }

    content.push('BT');
    content.push(`1 0 0 1 ${PAGE_MARGIN_X} ${y} Tm`);

    line.segments.forEach((segment, index) => {
      content.push(`/${segment.bold ? 'F2' : 'F1'} ${line.fontSize} Tf`);
      content.push(`(${escapePdfText(segment.text)}) Tj`);

      if (index < line.segments.length - 1) {
        const segmentWidth = measureText(segment.text, { bold: segment.bold, fontSize: line.fontSize });
        content.push(`${segmentWidth.toFixed(2)} 0 Td`);
      }
    });

    content.push('ET');
    y -= LINE_HEIGHT;
  });

  return content.join('\n');
};

const buildPdfBlob = async (profile) => {
  const headerImage = await buildHeaderImage();
  const objects = [];

  const addObject = (content) => {
    objects.push(content);
    return objects.length;
  };

  const fontRegularObjectId = addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
  const fontBoldObjectId = addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');

  let imageObjectId = null;
  if (headerImage) {
    const imageBinary = atob(headerImage.dataUrl.split(',')[1] || '');
    imageObjectId = addObject(
      `<< /Type /XObject /Subtype /Image /Width ${headerImage.pixelWidth} /Height ${headerImage.pixelHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${imageBinary.length} >>\nstream\n${imageBinary}\nendstream`
    );
  }

  const pageStream = buildPageStream(profile, imageObjectId ? 'Im1' : null);
  const contentObjectId = addObject(`<< /Length ${pageStream.length} >>\nstream\n${pageStream}\nendstream`);
  const pageObjectId = addObject('');
  const pagesObjectId = addObject('');
  const catalogObjectId = addObject(`<< /Type /Catalog /Pages ${pagesObjectId} 0 R >>`);

  const xObjectResource = imageObjectId ? ` /XObject << /Im1 ${imageObjectId} 0 R >>` : '';

  objects[pageObjectId - 1] = `<< /Type /Page /Parent ${pagesObjectId} 0 R /MediaBox [0 0 ${PDF_POINTS_WIDTH} ${PDF_POINTS_HEIGHT}] /Resources << /Font << /F1 ${fontRegularObjectId} 0 R /F2 ${fontBoldObjectId} 0 R >>${xObjectResource} >> /Contents ${contentObjectId} 0 R >>`;
  objects[pagesObjectId - 1] = `<< /Type /Pages /Count 1 /Kids [${pageObjectId} 0 R] >>`;

  let pdf = '%PDF-1.4\n';
  const offsets = [0];

  objects.forEach((object, index) => {
    offsets.push(pdf.length);
    pdf += `${index + 1} 0 obj\n${object}\nendobj\n`;
  });

  const xrefOffset = pdf.length;
  pdf += `xref\n0 ${objects.length + 1}\n`;
  pdf += '0000000000 65535 f \n';
  offsets.slice(1).forEach((offset) => {
    pdf += `${String(offset).padStart(10, '0')} 00000 n \n`;
  });
  pdf += `trailer\n<< /Size ${objects.length + 1} /Root ${catalogObjectId} 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;

  return new Blob([pdf], { type: 'application/pdf' });
};

export const downloadMembershipConfirmationLetter = async (profile) => {
  const blob = await buildPdfBlob(profile);
  const memberId = profile?.profile_info?.member_id || 'member';
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');

  link.href = url;
  link.download = `aac-membership-confirmation-${memberId}.pdf`;
  document.body.appendChild(link);
  link.click();
  link.remove();

  window.setTimeout(() => {
    URL.revokeObjectURL(url);
  }, 1000);
};
