<!-- Copyright 1997-1998 by Charles L. Taylor -->
<script type="text/javascript">

  <!--

  var pi = 3.14159265358979;

  /* Ellipsoid model constants (actual values here are for WGS84) */
  var sm_a = 6378137.0;
  var sm_b = 6356752.314;
  var sm_EccSquared = 6.69437999013e-03;

  var UTMScaleFactor = 0.9996;

  /*
   * DegToRad
   *
   * Converts degrees to radians.
   *
   */
  function DegToRad (deg)
  {
    return (deg / 180.0 * pi)
  }

  /*
   * RadToDeg
   *
   * Converts radians to degrees.
   *
   */
  function RadToDeg (rad)
  {
    return (rad / pi * 180.0)
  }

  /*
   * ArcLengthOfMeridian
   *
   * Computes the ellipsoidal distance from the equator to a point at a
   * given latitude.
   *
   * Reference: Hoffmann-Wellenhof, B., Lichtenegger, H., and Collins, J.,
   * GPS: Theory and Practice, 3rd ed.  New York: Springer-Verlag Wien, 1994.
   *
   * Inputs:
   *     phi - Latitude of the point, in radians.
   *
   * Globals:
   *     sm_a - Ellipsoid model major axis.
   *     sm_b - Ellipsoid model minor axis.
   *
   * Returns:
   *     The ellipsoidal distance of the point from the equator, in meters.
   *
   */
  function ArcLengthOfMeridian (phi)
  {
    var alpha, beta, gamma, delta, epsilon, n;
    var result;

    /* Precalculate n */
    n = (sm_a - sm_b) / (sm_a + sm_b);

    /* Precalculate alpha */
    alpha = ((sm_a + sm_b) / 2.0)
      * (1.0 + (Math.pow (n, 2.0) / 4.0) + (Math.pow (n, 4.0) / 64.0));

    /* Precalculate beta */
    beta = (-3.0 * n / 2.0) + (9.0 * Math.pow (n, 3.0) / 16.0)
      + (-3.0 * Math.pow (n, 5.0) / 32.0);

    /* Precalculate gamma */
    gamma = (15.0 * Math.pow (n, 2.0) / 16.0)
      + (-15.0 * Math.pow (n, 4.0) / 32.0);

    /* Precalculate delta */
    delta = (-35.0 * Math.pow (n, 3.0) / 48.0)
      + (105.0 * Math.pow (n, 5.0) / 256.0);

    /* Precalculate epsilon */
    epsilon = (315.0 * Math.pow (n, 4.0) / 512.0);

    /* Now calculate the sum of the series and return */
    result = alpha
      * (phi + (beta * Math.sin (2.0 * phi))
      + (gamma * Math.sin (4.0 * phi))
      + (delta * Math.sin (6.0 * phi))
      + (epsilon * Math.sin (8.0 * phi)));

    return result;
  }

  /*
   * UTMCentralMeridian
   *
   * Determines the central meridian for the given UTM zone.
   *
   * Inputs:
   *     zone - An integer value designating the UTM zone, range [1,60].
   *
   * Returns:
   *   The central meridian for the given UTM zone, in radians, or zero
   *   if the UTM zone parameter is outside the range [1,60].
   *   Range of the central meridian is the radian equivalent of [-177,+177].
   *
   */
  function UTMCentralMeridian (zone)
  {
    var cmeridian;

    cmeridian = DegToRad (-183.0 + (zone * 6.0));

    return cmeridian;
  }

  /*
   * FootpointLatitude
   *
   * Computes the footpoint latitude for use in converting transverse
   * Mercator coordinates to ellipsoidal coordinates.
   *
   * Reference: Hoffmann-Wellenhof, B., Lichtenegger, H., and Collins, J.,
   *   GPS: Theory and Practice, 3rd ed.  New York: Springer-Verlag Wien, 1994.
   *
   * Inputs:
   *   y - The UTM northing coordinate, in meters.
   *
   * Returns:
   *   The footpoint latitude, in radians.
   *
   */
  function FootpointLatitude (y)
  {
    var y_, alpha_, beta_, gamma_, delta_, epsilon_, n;
    var result;

    /* Precalculate n (Eq. 10.18) */
    n = (sm_a - sm_b) / (sm_a + sm_b);

    /* Precalculate alpha_ (Eq. 10.22) */
    /* (Same as alpha in Eq. 10.17) */
    alpha_ = ((sm_a + sm_b) / 2.0)
      * (1 + (Math.pow (n, 2.0) / 4) + (Math.pow (n, 4.0) / 64));

    /* Precalculate y_ (Eq. 10.23) */
    y_ = y / alpha_;

    /* Precalculate beta_ (Eq. 10.22) */
    beta_ = (3.0 * n / 2.0) + (-27.0 * Math.pow (n, 3.0) / 32.0)
      + (269.0 * Math.pow (n, 5.0) / 512.0);

    /* Precalculate gamma_ (Eq. 10.22) */
    gamma_ = (21.0 * Math.pow (n, 2.0) / 16.0)
      + (-55.0 * Math.pow (n, 4.0) / 32.0);

    /* Precalculate delta_ (Eq. 10.22) */
    delta_ = (151.0 * Math.pow (n, 3.0) / 96.0)
      + (-417.0 * Math.pow (n, 5.0) / 128.0);

    /* Precalculate epsilon_ (Eq. 10.22) */
    epsilon_ = (1097.0 * Math.pow (n, 4.0) / 512.0);

    /* Now calculate the sum of the series (Eq. 10.21) */
    result = y_ + (beta_ * Math.sin (2.0 * y_))
      + (gamma_ * Math.sin (4.0 * y_))
      + (delta_ * Math.sin (6.0 * y_))
      + (epsilon_ * Math.sin (8.0 * y_));

    return result;
  }

  /*
   * MapLatLonToXY
   *
   * Converts a latitude/longitude pair to x and y coordinates in the
   * Transverse Mercator projection.  Note that Transverse Mercator is not
   * the same as UTM; a scale factor is required to convert between them.
   *
   * Reference: Hoffmann-Wellenhof, B., Lichtenegger, H., and Collins, J.,
   * GPS: Theory and Practice, 3rd ed.  New York: Springer-Verlag Wien, 1994.
   *
   * Inputs:
   *    phi - Latitude of the point, in radians.
   *    lambda - Longitude of the point, in radians.
   *    lambda0 - Longitude of the central meridian to be used, in radians.
   *
   * Outputs:
   *    xy - A 2-element array containing the x and y coordinates
   *         of the computed point.
   *
   * Returns:
   *    The function does not return a value.
   *
   */
  function MapLatLonToXY (phi, lambda, lambda0, xy)
  {
    var N, nu2, ep2, t, t2, l;
    var l3coef, l4coef, l5coef, l6coef, l7coef, l8coef;
    var tmp;

    /* Precalculate ep2 */
    ep2 = (Math.pow (sm_a, 2.0) - Math.pow (sm_b, 2.0)) / Math.pow (sm_b, 2.0);

    /* Precalculate nu2 */
    nu2 = ep2 * Math.pow (Math.cos (phi), 2.0);

    /* Precalculate N */
    N = Math.pow (sm_a, 2.0) / (sm_b * Math.sqrt (1 + nu2));

    /* Precalculate t */
    t = Math.tan (phi);
    t2 = t * t;
    tmp = (t2 * t2 * t2) - Math.pow (t, 6.0);

    /* Precalculate l */
    l = lambda - lambda0;

    /* Precalculate coefficients for l**n in the equations below
        so a normal human being can read the expressions for easting
        and northing
        -- l**1 and l**2 have coefficients of 1.0 */
    l3coef = 1.0 - t2 + nu2;

    l4coef = 5.0 - t2 + 9 * nu2 + 4.0 * (nu2 * nu2);

    l5coef = 5.0 - 18.0 * t2 + (t2 * t2) + 14.0 * nu2
      - 58.0 * t2 * nu2;

    l6coef = 61.0 - 58.0 * t2 + (t2 * t2) + 270.0 * nu2
      - 330.0 * t2 * nu2;

    l7coef = 61.0 - 479.0 * t2 + 179.0 * (t2 * t2) - (t2 * t2 * t2);

    l8coef = 1385.0 - 3111.0 * t2 + 543.0 * (t2 * t2) - (t2 * t2 * t2);

    /* Calculate easting (x) */
    xy[0] = N * Math.cos (phi) * l
      + (N / 6.0 * Math.pow (Math.cos (phi), 3.0) * l3coef * Math.pow (l, 3.0))
      + (N / 120.0 * Math.pow (Math.cos (phi), 5.0) * l5coef * Math.pow (l, 5.0))
      + (N / 5040.0 * Math.pow (Math.cos (phi), 7.0) * l7coef * Math.pow (l, 7.0));

    /* Calculate northing (y) */
    xy[1] = ArcLengthOfMeridian (phi)
      + (t / 2.0 * N * Math.pow (Math.cos (phi), 2.0) * Math.pow (l, 2.0))
      + (t / 24.0 * N * Math.pow (Math.cos (phi), 4.0) * l4coef * Math.pow (l, 4.0))
      + (t / 720.0 * N * Math.pow (Math.cos (phi), 6.0) * l6coef * Math.pow (l, 6.0))
      + (t / 40320.0 * N * Math.pow (Math.cos (phi), 8.0) * l8coef * Math.pow (l, 8.0));

    return;
  }

  /*
   * MapXYToLatLon
   *
   * Converts x and y coordinates in the Transverse Mercator projection to
   * a latitude/longitude pair.  Note that Transverse Mercator is not
   * the same as UTM; a scale factor is required to convert between them.
   *
   * Reference: Hoffmann-Wellenhof, B., Lichtenegger, H., and Collins, J.,
   *   GPS: Theory and Practice, 3rd ed.  New York: Springer-Verlag Wien, 1994.
   *
   * Inputs:
   *   x - The easting of the point, in meters.
   *   y - The northing of the point, in meters.
   *   lambda0 - Longitude of the central meridian to be used, in radians.
   *
   * Outputs:
   *   philambda - A 2-element containing the latitude and longitude
   *               in radians.
   *
   * Returns:
   *   The function does not return a value.
   *
   * Remarks:
   *   The local variables Nf, nuf2, tf, and tf2 serve the same purpose as
   *   N, nu2, t, and t2 in MapLatLonToXY, but they are computed with respect
   *   to the footpoint latitude phif.
   *
   *   x1frac, x2frac, x2poly, x3poly, etc. are to enhance readability and
   *   to optimize computations.
   *
   */
  function MapXYToLatLon (x, y, lambda0, philambda)
  {
    var phif, Nf, Nfpow, nuf2, ep2, tf, tf2, tf4, cf;
    var x1frac, x2frac, x3frac, x4frac, x5frac, x6frac, x7frac, x8frac;
    var x2poly, x3poly, x4poly, x5poly, x6poly, x7poly, x8poly;

    /* Get the value of phif, the footpoint latitude. */
    phif = FootpointLatitude (y);

    /* Precalculate ep2 */
    ep2 = (Math.pow (sm_a, 2.0) - Math.pow (sm_b, 2.0))
      / Math.pow (sm_b, 2.0);

    /* Precalculate cos (phif) */
    cf = Math.cos (phif);

    /* Precalculate nuf2 */
    nuf2 = ep2 * Math.pow (cf, 2.0);

    /* Precalculate Nf and initialize Nfpow */
    Nf = Math.pow (sm_a, 2.0) / (sm_b * Math.sqrt (1 + nuf2));
    Nfpow = Nf;

    /* Precalculate tf */
    tf = Math.tan (phif);
    tf2 = tf * tf;
    tf4 = tf2 * tf2;

    /* Precalculate fractional coefficients for x**n in the equations
        below to simplify the expressions for latitude and longitude. */
    x1frac = 1.0 / (Nfpow * cf);

    Nfpow *= Nf;   /* now equals Nf**2) */
    x2frac = tf / (2.0 * Nfpow);

    Nfpow *= Nf;   /* now equals Nf**3) */
    x3frac = 1.0 / (6.0 * Nfpow * cf);

    Nfpow *= Nf;   /* now equals Nf**4) */
    x4frac = tf / (24.0 * Nfpow);

    Nfpow *= Nf;   /* now equals Nf**5) */
    x5frac = 1.0 / (120.0 * Nfpow * cf);

    Nfpow *= Nf;   /* now equals Nf**6) */
    x6frac = tf / (720.0 * Nfpow);

    Nfpow *= Nf;   /* now equals Nf**7) */
    x7frac = 1.0 / (5040.0 * Nfpow * cf);

    Nfpow *= Nf;   /* now equals Nf**8) */
    x8frac = tf / (40320.0 * Nfpow);

    /* Precalculate polynomial coefficients for x**n.
        -- x**1 does not have a polynomial coefficient. */
    x2poly = -1.0 - nuf2;

    x3poly = -1.0 - 2 * tf2 - nuf2;

    x4poly = 5.0 + 3.0 * tf2 + 6.0 * nuf2 - 6.0 * tf2 * nuf2
      - 3.0 * (nuf2 *nuf2) - 9.0 * tf2 * (nuf2 * nuf2);

    x5poly = 5.0 + 28.0 * tf2 + 24.0 * tf4 + 6.0 * nuf2 + 8.0 * tf2 * nuf2;

    x6poly = -61.0 - 90.0 * tf2 - 45.0 * tf4 - 107.0 * nuf2
      + 162.0 * tf2 * nuf2;

    x7poly = -61.0 - 662.0 * tf2 - 1320.0 * tf4 - 720.0 * (tf4 * tf2);

    x8poly = 1385.0 + 3633.0 * tf2 + 4095.0 * tf4 + 1575 * (tf4 * tf2);

    /* Calculate latitude */
    philambda[0] = phif + x2frac * x2poly * (x * x)
      + x4frac * x4poly * Math.pow (x, 4.0)
      + x6frac * x6poly * Math.pow (x, 6.0)
      + x8frac * x8poly * Math.pow (x, 8.0);

    /* Calculate longitude */
    philambda[1] = lambda0 + x1frac * x
      + x3frac * x3poly * Math.pow (x, 3.0)
      + x5frac * x5poly * Math.pow (x, 5.0)
      + x7frac * x7poly * Math.pow (x, 7.0);

    return;
  }


  /*
   * LatLonToUTMXY
   *
   * Converts a latitude/longitude pair to x and y coordinates in the
   * Universal Transverse Mercator projection.
   *
   * Inputs:
   *   lat - Latitude of the point, in radians.
   *   lon - Longitude of the point, in radians.
   *   zone - UTM zone to be used for calculating values for x and y.
   *          If zone is less than 1 or greater than 60, the routine
   *          will determine the appropriate zone from the value of lon.
   *
   * Outputs:
   *   xy - A 2-element array where the UTM x and y values will be stored.
   *
   * Returns:
   *   The UTM zone used for calculating the values of x and y.
   *
   */
  function LatLonToUTMXY (lat, lon, zone, xy)
  {
    MapLatLonToXY (lat, lon, UTMCentralMeridian (zone), xy);

    /* Adjust easting and northing for UTM system. */
    xy[0] = xy[0] * UTMScaleFactor + 500000.0;
    xy[1] = xy[1] * UTMScaleFactor;
    if (xy[1] < 0.0)
      xy[1] = xy[1] + 10000000.0;

    return zone;
  }

  /*
   * UTMXYToLatLon
   *
   * Converts x and y coordinates in the Universal Transverse Mercator
   * projection to a latitude/longitude pair.
   *
   * Inputs:
   *  x - The easting of the point, in meters.
   *	y - The northing of the point, in meters.
   *	zone - The UTM zone in which the point lies.
   *	southhemi - True if the point is in the southern hemisphere;
   *               false otherwise.
   *
   * Outputs:
   *	latlon - A 2-element array containing the latitude and
   *            longitude of the point, in radians.
   *
   * Returns:
   *	The function does not return a value.
   *
   */
  function UTMXYToLatLon (x, y, zone, southhemi, latlon)
  {
    var cmeridian;

    x -= 500000.0;
    x /= UTMScaleFactor;

    /* If in southern hemisphere, adjust y accordingly. */
    if (southhemi)
      y -= 10000000.0;

    y /= UTMScaleFactor;

    cmeridian = UTMCentralMeridian (zone);
    MapXYToLatLon (x, y, cmeridian, latlon);

    return;
  }
  /*
   * btnToUTM_OnClick
   *
   * Called when the btnToUTM button is clicked.
   *
   */
  function btnToUTM_OnClick ()
  {
    var xy = new Array(2);

    if (isNaN (parseFloat (document.frmConverter.txtLongitude.value))) {
      alert ("Please enter a valid longitude in the lon field.");
      return false;
    }

    lon = parseFloat (document.frmConverter.txtLongitude.value);

    if ((lon < -180.0) || (180.0 <= lon)) {
      alert ("The longitude you entered is out of range.  " +
        "Please enter a number in the range [-180, 180).");
      return false;
    }

    if (isNaN (parseFloat (document.frmConverter.txtLatitude.value))) {
      alert ("Please enter a valid latitude in the lat field.");
      return false;
    }

    lat = parseFloat (document.frmConverter.txtLatitude.value);

    if ((lat < -90.0) || (90.0 < lat)) {
      alert ("The latitude you entered is out of range.  " +
        "Please enter a number in the range [-90, 90].");
      return false;
    }

    // Compute the UTM zone.
    zone = Math.floor ((lon + 180.0) / 6) + 1;

    zone = LatLonToUTMXY (DegToRad (lat), DegToRad (lon), zone, xy);

    /* Set the output controls.  */
    document.frmConverter.txtX.value = xy[0];
    document.frmConverter.txtY.value = xy[1];
    document.frmConverter.txtZone.value = zone;
    if (lat < 0)
    // Set the S button.
      document.frmConverter.rbtnHemisphere[1].checked = true;
    else
    // Set the N button.
      document.frmConverter.rbtnHemisphere[0].checked = true;

    return true;
  }

  /*
   * btnToGeographic_OnClick
   *
   * Called when the btnToGeographic button is clicked.
   *
   */
  function btnToGeographic_OnClick ()
  {
    latlon = new Array(2);
    var x, y, zone, southhemi;

    if (isNaN (parseFloat (document.frmConverter.txtX.value))) {
      alert ("Please enter a valid easting in the x field.");
      return false;
    }

    x = parseFloat (document.frmConverter.txtX.value);

    if (isNaN (parseFloat (document.frmConverter.txtY.value))) {
      alert ("Please enter a valid northing in the y field.");
      return false;
    }

    y = parseFloat (document.frmConverter.txtY.value);

    if (isNaN (parseInt (document.frmConverter.txtZone.value))) {
      alert ("Please enter a valid UTM zone in the zone field.");
      return false;
    }

    zone = parseFloat (document.frmConverter.txtZone.value);

    if ((zone < 1) || (60 < zone)) {
      alert ("The UTM zone you entered is out of range.  " +
        "Please enter a number in the range [1, 60].");
      return false;
    }

    if (document.frmConverter.rbtnHemisphere[1].checked == true)
      southhemi = true;
    else
      southhemi = false;

    UTMXYToLatLon (x, y, zone, southhemi, latlon);

    document.frmConverter.txtLongitude.value = RadToDeg (latlon[1]);
    document.frmConverter.txtLatitude.value = RadToDeg (latlon[0]);

    return true;
  }
  //    -->
</script>
<?php
foreach ($krt as $baris):
  $id = $baris->id;
  $no_kip = $baris->no_kip;
  $nama = $baris->nama_krt;
  $umur = $baris->umur_krt_pendataan;
  $jk = $baris->jenis_kelamin;
  $alamat = $baris->alamat;
  $jml_keluarga = $baris->jml_keluarga;
  $jml_individu = $baris->jml_individu;
  $td_pengenal = $baris->ada_ktp;
  $lapus = $baris->nama_lapus;
  $st_kerja = $baris->nama_stkerja;
  $st_ksj = $baris->status_kesejahteraan;
  $st_tmptgl = $baris->nama_strumah;
  $jenis_atap = $baris->nama_atap;
  $jenis_dinding = $baris->nama_dinding;
  $jenis_lantai = $baris->nama_lantai;
  $air_minum = $baris->nama_air;
  $sumber_listrik = $baris->nama_listrik;
  $bb_memasak = $baris->nama_masak;
  $tmpat_bab = $baris->nama_tmpat;
  $tmpat_tinja = $baris->tempat_tinja;
  $x = $baris->x;
  $y = $baris->y;
  $is_2008 = $baris->is_2008;
endforeach;

foreach ($detail_krt as $dkrt):
  $nama_kab = $dkrt->nama_kab;
  $nama_kec = $dkrt->nama_kec;
  $nama_des = $dkrt->nama_des;
  $sekolah = $dkrt->nama_sekolah;
  $ijazah = $dkrt->nama_ijasah;
  $kelas = $dkrt->kelas;
  $bln_lhr = $dkrt->bln_lhr;
  $thn_lhr = $dkrt->th_lhr;
  $cacat = $dkrt->nama_cacat;
  $penyakit = $dkrt->nama_penyakit;
endforeach;

?>
<h2>
  KEPALA RUMAH TANGGA SASARAN "<?php echo $nama; ?>"
   <?php 
    if($jamkesda > 0)  { ?>
      <img src="<?php echo base_url() ?>asset/images/icons/accept.png" title="Telah Menerima Jamkesda" />
  <?php
    } 
  ?>
</h2>
<p>
  <?php
  if ($this->tank_auth->check_group('admin')) {
    echo anchor('admin/ubah_krtsebelas/' . $id, 'Ubah') . ' | ' . anchor('admin/tambahfoto_krtsebelas/' . $id, 'Tambah Foto');
  }
  if ($this->tank_auth->check_group('manager')) {
    echo anchor('manager/ubah_krtsebelas/' . $id, 'Ubah') . ' | ' . anchor('manager/tambahfoto_krtsebelas/' . $id, 'Tambah Foto');
  }
  if ($this->tank_auth->check_group('operator')) {
    echo anchor('operator/ubah_krtsebelas/' . $id, 'Ubah') . ' | ' . anchor('operator/tambahfoto_krtsebelas/' . $id, 'Tambah Foto');
  }
  ?>
</p>
<table width="300px" class="datatable" style="float:left;width:300px;">
  <tr>
    <td><h3 style="font:italic bold 11px Georgia, serif; color: #688104">Data Pribadi</h3></td>
  <tr>
    <td>No. KIP</td>
    <td>:</td>
    <td>
      <?php echo $no_kip; ?>
    </td>
  </tr>
  <tr>
    <td>Nama</td>
    <td>:</td>
    <td>
      <?php echo $nama; ?>
    </td>
  </tr>
  <tr>
    <td>Umur</td>
    <td>:</td>
    <td>
      <?php
      $now = date('Y');
      $now = $now - $thn_lhr;
      echo $now . " tahun";
      echo " (" . $bln_lhr . " - " . $thn_lhr . ")";
      ?>
    </td>
  </tr>
  <tr>
    <td>Jenis Kelamin</td>
    <td>:</td>
    <td>
      <?php echo $jk; ?>
    </td>
  </tr>
  <tr>
    <td>Alamat</td>
    <td>:</td>
    <td>
      <?php echo $alamat; ?>
    </td>
  </tr>
  <tr>
    <td>Kabupaten / Kota</td>
    <td>:</td>
    <td>
      <?php
      echo $nama_kab;
      ?>
    </td>
  </tr>
  <tr>
    <td>Kecamatan</td>
    <td>:</td>
    <td>
      <?php echo $nama_kec; ?>
    </td>
  </tr>
  <tr>
    <td>Kelurahan</td>
    <td>:</td>
    <td>
      <?php echo $nama_des; ?>
    </td>
  </tr>
  <tr>
    <td>Jumlah Keluarga</td>
    <td>:</td>
    <td>
      <?php echo $jml_keluarga; ?>
    </td>
  </tr>
  <tr>
    <td>Jumlah Individu</td>
    <td>:</td>
    <td>
      <?php echo $jml_individu; ?>
      orang
    </td>
  </tr>
  <tr>
    <td>Tanda Pengenal</td>
    <td>:</td>
    <td>
      <?php echo $td_pengenal; ?>
    </td>
  </tr>
  <tr>
    <td><h3 style="font:italic bold 11px Georgia, serif; color: #688104;">Pendidikan</h3></td>
  </tr>
  <tr>
    <td>Sekolah</td>
    <td>:</td>
    <td>
      <?php echo $sekolah; ?>
    </td>
  </tr>
  <tr>
    <td>Ijazah</td>
    <td>:</td>
    <td>
      <?php echo $ijazah; ?>
    </td>
  </tr>
  <tr>
    <td>Kelas</td>
    <td>:</td>
    <td>
      <?php echo $kelas; ?>
    </td>
  </tr>
  <tr>
    <td><h3 style="font:italic bold 11px Georgia, serif; color: #688104;">Pekerjaan</h3></td>
  </tr>
  <tr>
    <td>Lap. Usaha</td>
    <td>:</td>
    <td>
      <?php echo $lapus; ?>
    </td>
  </tr>
  <tr>
    <td>Stat. Kerja</td>
    <td>:</td>
    <td>
      <?php echo $st_kerja; ?>
    </td>
  </tr>
  <tr>
    <td>Klasifikasi</td>
    <td>:</td>
    <td>
      <?php echo $st_ksj; ?>
    </td>
  </tr>
  <tr>
    <td><h3 style="font:italic bold 11px Georgia, serif; color: #688104;">Tempat Tinggal</h3></td>
  </tr>
  <tr>
    <td>Stat. Rumah</td>
    <td>:</td>
    <td>
      <?php echo $st_tmptgl; ?>
    </td>
  </tr>
  <tr>
    <td>Jenis Atap</td>
    <td>:</td>
    <td>
      <?php echo $jenis_atap; ?>
    </td>
  </tr>
  <tr>
    <td>Jenis Dinding</td>
    <td>:</td>
    <td>
      <?php echo $jenis_dinding; ?>
    </td>
  </tr>
  <tr>
    <td>Jenis Lantai</td>
    <td>:</td>
    <td>
      <?php echo $jenis_lantai; ?>
    </td>
  </tr>
  <tr>
    <td>Sumber Air Minum</td>
    <td>:</td>
    <td>
      <?php echo $air_minum; ?>
    </td>
  </tr>
  <tr>
    <td>Sumber Listrik</td>
    <td>:</td>
    <td>
      <?php echo $sumber_listrik; ?>
    </td>
  </tr>
  <tr>
    <td>Bahan Bakar Memasak</td>
    <td>:</td>
    <td>
      <?php echo $bb_memasak; ?>
    </td>
  </tr>
  <tr>
    <td>Tempat BAB</td>
    <td>:</td>
    <td>
      <?php echo $tmpat_bab; ?>
    </td>
  </tr>
  <tr>
    <td>Tempat Tinja</td>
    <td>:</td>
    <td>
      <?php echo $tmpat_tinja; ?>
    </td>
  </tr>
  <tr>
    <td><h3 style="font:italic bold 11px Georgia, serif; color: #688104;">Kesehatan</h3></td>
  </tr>
  <tr>
    <td>Cacat</td>
    <td>:</td>
    <td>
      <?php echo $cacat; ?>
    </td>
  </tr>
  <tr>
    <td>Penyakit</td>
    <td>:</td>
    <td>
      <?php echo $penyakit; ?>
    </td>
  </tr>
</table>
<div id="gallery" style="float: left;width:360px; ">
  <h3>
    &nbsp;&nbsp; FOTO 
  </h3>
  <?php $fnum = 0 ?>
  <?php foreach ($foto as $row) : ?>
    <?php $fnum++ ?>
    <table style="float:left; width:170px; margin: 5px;">
      <tr>
        <td colspan="2">
          <b>
            <?php echo $row->nama_kategori; ?>
          </b>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <?php if ($row->foto) : ?>
            <?php if ($row->status == '0') { ?>
              <img width="155" title="" src="<?php echo base_url() . 'asset/images/default_photo2.jpg'; ?>" />
            <?php } else { ?>
              <a href="<?php echo base_url() . 'asset/upload/rumah/' . $row->foto; ?>" target="_blank">
		<?php
			if($is_2008 == '1') :
				$url_2008 = "http://pusdalisbang.jabarprov.go.id/appkemiskinan/";
?>
				
				<img width="155" title="<?php echo $row->nama_kategori; ?>" src="<?php echo $url_2008 . 'asset/upload/rumah/' . $row->foto; ?>" />
		<?php
			 else :
		?>
                <img width="155" title="<?php echo $row->nama_kategori; ?>" src="<?php echo base_url() . 'asset/upload/rumah/' . $row->foto; ?>" />
              </a>
            <?php 
		endif;		
		} ?>
          <?php else : ?>
            <img width="155" title="" src="<?php echo base_url() . 'asset/images/default_photo.jpg'; ?>" />
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <td colspan="2" style="text-align: center;">
          <?php echo $row->keterangan ? $row->keterangan . '<br />' : ''//echo $row->foto?'':'Belum Tersedia' ?>
          <?php
          if ($this->tank_auth->check_group('admin') && $row->foto) {
            echo anchor('admin/ubahkategori_foto/' . $row->id, 'Ubah') . ' | ' . anchor('admin/hapus_foto/' . $row->id, 'Hapus', array('onclick' => "return confirm('Hapus foto " . $row->nama_kategori. "?')")) . ' | ' .
            anchor('foto_rumah/ubahStatus/' . $row->id_krt . '/' . $row->id_kategori, 'Ubah Status');
          }
          if ($this->tank_auth->check_group('manager') && $row->foto) {
            echo anchor('manager/ubahkategori_foto/' . $row->id, 'Ubah') . ' | ' . anchor('manager/hapus_foto/' . $row->id, 'Hapus', array('onclick' => "return confirm('Hapus foto " . $row->nama_kategori . "?')"));
          }
          if ($this->tank_auth->check_group('operator') && $row->foto) {
            echo anchor('operator/ubahkategori_foto/' . $row->id, 'Ubah') . ' | ' . anchor('operator/hapus_foto/' . $row->id, 'Hapus', array('onclick' => "return confirm('Hapus foto " . $row->nama_kategori . "?')"));
          }
          ?>
        </td>

      </tr>
    </table>
    <?php echo $fnum % 2 == 0 ? '<div style="clear:left"></div>' : '' ?>
    <?php
  endforeach;
  ?>
</div>
<div style="clear:both;">
</div>
<br />
<h3>Anggota Rumah Tangga</h3>
<?php
if ($this->tank_auth->check_group('admin')) {
  echo anchor('admin/tambah_art/' . $id, 'Tambah Anggota Rumah Tangga');
}
if ($this->tank_auth->check_group('manager')) {
  echo anchor('manager/tambah_art/' . $id, 'Tambah Anggota Rumah Tangga');
}
if ($this->tank_auth->check_group('operator')) {
  echo anchor('operator/tambah_art/' . $krtid, 'Tambah Anggota Rumah Tangga');
}
?>

<div class="content-scroll">
  <table class="datatable full" style="font-size: 10px;">
    <thead>
      <tr>
        <th>Nama</th>
        <th>Hub. Krt</th>
        <th>Hub. KK</th>
        <th>JK</th>
        <th>Umur</th>
        <th>KTP</th>
        <th>Cacat</th>
        <th>Penyakit</th>
        <th>Ijazah</th>
        <th>Sekolah</th>
        <th>Lap. Usaha</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $i = 0;
      foreach ($list_art as $row) {
        $i++;
        ?>
        <tr>
          <td>
            <?php echo $row->nama_art; ?>
          </td>
          <td>
            <?php echo $row->nam_hubkrt; ?>
          </td>
          <td>
            <?php echo $row->nama_hubkk; ?>
          </td>
          <td>
            <?php echo $row->jenis_kelamin; ?>
          </td>
          <td>
            <?php
            $years = date('Y');
            echo $years - $row->th_lhr;
            echo " (" . $row->bln_lhr . " - " . $row->th_lhr . ")";
            ?>
          </td>
          <td>
            <?php echo $row->ada_ktp; ?>
          </td>
          <td>
            <?php echo $row->nama_cacat; ?>
          </td>
          <td>
            <?php echo $row->nama_penyakit; ?>
          </td>
          <td>
            <?php echo $row->nama_ijasah; ?>
          </td>
          <td>
            <?php echo $row->nama_sekolah; ?>
          </td>
          <td>
            <?php echo $row->nama_lapus; ?>
          </td>

          <td class="tiptip">
            <?php if ($this->tank_auth->check_group('admin')) : ?>
              <a href="<?php echo site_url('admin/ubah_art/' . $row->id) ?>" class="button button-gray no-text" title="Ubah">
                <span class="pencil"></span>
              </a>
              <a onclick="return confirm('Hapus?')" href="<?php echo site_url('admin/hapus_hapus/' . $row->id) ?>" class="button button-red no-text" title="Hapus">
                <span class="bin"></span>
              </a>
            <?php endif; ?>
            <?php if ($this->tank_auth->check_group('manager')) : ?>
              <a href="<?php echo site_url('manager/ubah_art/' . $row->id) ?>" class="button button-gray no-text" title="Ubah">
                <span class="pencil"></span>
              </a>
              <a onclick="return confirm('Hapus?')" href="<?php echo site_url('manager/hapus_hapus/' . $row->id) ?>" class="button button-red no-text" title="Hapus">
                <span class="bin"></span>
              </a>
            <?php endif; ?>
            <?php if ($this->tank_auth->check_group('operator')) : ?>
              <a href="<?php echo site_url('operator/ubah_art/' . $row->id) ?>" class="button button-gray no-text" title="Ubah">
                <span class="pencil"></span>
              </a>
              <a onclick="return confirm('Hapus?')" href="<?php echo site_url('operator/hapus_hapus/' . $row->id) ?>" class="button button-red no-text" title="Hapus">
                <span class="bin"></span>
              </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php
      }
      if (!$list_art)
        echo '<tr><td colspan="13">Tidak memiliki annggota rumah tangga</tr>';
      ?>
    </tbody>
  </table>
</div>
<br />
<br />
<script type="text/javascript" src="http://maps.googleapis.com/maps/api/js?key=AIzaSyDmwo-xWbGQfmvRnYWZu2Wps6OHaxQXD2o&sensor=false&libraries=geometry"></script>
<script type="text/javascript">
   
  function initialize() {
	    
    var latlon = new Array(2);
    var x = <?php echo $x; ?>;
    var y = <?php echo $y; ?>;
    var x1 = <?php echo $balkot->l; ?>;
    var y1 = <?php echo $balkot->b; ?>;
    var southhemi = true;
    var zone = 48;
    var lat = -6.878412;
    var lon = 107.616416;

    if(x != 0 && y != 0) {
      if(x < 260000) {
        zone = 49;				

        UTMXYToLatLon (x, y, zone, southhemi, latlon);
		        
        lon = RadToDeg (latlon[1]);
        lat = RadToDeg (latlon[0]);
      } else {
        zone = 48;				

        UTMXYToLatLon (x, y, zone, southhemi, latlon);
		        
        lon = RadToDeg (latlon[1]);
        lat = RadToDeg (latlon[0]);
      }    
    }
    var km0 = new google.maps.LatLng(-6.902071,107.61876);
    var krt = new google.maps.LatLng(lat,lon);
    var bk = new google.maps.LatLng(x1,y1);
    
    var myOptions = {
      center: bk,
      zoom: 9,
      minZoom: 8,
      panControl: false,
      zoomControl: true,
      streetViewControl: false,
      scaleControl: false,
      mapTypeId: google.maps.MapTypeId.HYBRID
    }

    var map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
    map.setTilt(45);

    //gedung sate
    var contentString = '<div id="content">'+
      '<div id="siteNotice">'+
      '</div>'+
      '<h1 id="firstHeading" class="firstHeading"><span style="color: blue;">JABAR</span> KM 0 Pro Poor</h1>'+
      '<div id="bodyContent">'+
      '<p><b><i>JABAR</i> KM 0 Pro Poor</b>, <span style="color: blue;">Gedung Sate Bandung</span><br />'+
      'Jl. Diponegoro No.22 Bandung, Jawa Barat - Indonesia</p>'+
      '</div>'+
      '</div>';
			
    var infowindow = new google.maps.InfoWindow({
      content: contentString
    });		
    var image = "http://www.google.com/intl/en_us/mapfiles/ms/micons/blue-dot.png";
    var marker = new google.maps.Marker({
      position: km0,
      map: map,
      title:"KM 0 Pro Poor Jawa Barat",
      icon: image
    });
    google.maps.event.addListener(marker, 'click', function() {
      infowindow.open(map,marker);
    });
		

    //bk
    var contentString3 = '<div id="content">'+
      '<h1 id="firstHeading" class="firstHeading">Kantor Balaikota </h1>'+
      '<div id="bodyContent">'+
      '<p>Kantor Balaikota  <b><?php echo $balkot->nama_daerah; ?></b></p>'+
      '<?php echo $balkot->ALAMAT; ?>'+
      '</div>'+
      '</div>';
    var infowindow3 = new google.maps.InfoWindow({
      content: contentString3
    });

    var image3 = "http://www.google.com/intl/en_us/mapfiles/ms/micons/green-dot.png";
    var marker3 = new google.maps.Marker({
      position: bk,
      map: map,
      title:"Kantor Balaikota",
      icon: image3
    });
    google.maps.event.addListener(marker3, 'click', function() {
      infowindow3.open(map,marker3);
    });
		
    //krt
    var distance = Math.ceil(google.maps.geometry.spherical.computeDistanceBetween(km0, krt))/1000;	
    var distance2 = Math.ceil(google.maps.geometry.spherical.computeDistanceBetween(bk, krt))/1000;
	
    var contentString2 = '<div id="content">'+
      '<h1 id="firstHeading" class="firstHeading">RUMAH KEPALA RTS <br />"<?php echo $id; ?>"</h1>'+
      '<div id="bodyContent">'+
      '<p><b><?php  echo $nama; ?></b>, <?php echo $alamat; ?></p>'+
      '<p><b>'+distance.toFixed(2)+'</b> KM dari Gedung Sate &nbsp;&nbsp;&nbsp;&nbsp;<img width="25px" src="<?php echo base_url() ?>asset/images/gd-sate.jpg" /></p>'+
      '<p><b>'+distance2.toFixed(2)+'</b> KM dari Kantor Balaikotax <b><?php echo $balkot->nama_daerah; ?></b></p>'+
      '</div>'+
      '</div>';
			
    var infowindow2 = new google.maps.InfoWindow({
      content: contentString2
    });


    var image2 = "http://www.google.com/intl/en_us/mapfiles/ms/micons/red-dot.png";
    var marker2 = new google.maps.Marker({
      position: krt,
      map: map,
      title:"Rumah KRTS",
      icon: image2
    });
    google.maps.event.addListener(marker2, 'click', function() {
      infowindow2.open(map,marker2);
    });



    //garis penghubung km0->krt
    var flightPlanCoordinates = [
      km0,krt
    ];
    var flightPath = new google.maps.Polyline({
      path: flightPlanCoordinates,
      strokeColor: "#0000EB",
      strokeOpacity: 1.0,
      strokeWeight: 2
    });
	
    flightPath.setMap(map);


    //garis penghubung bk->krt
    var flightPlanCoordinates2 = [
      bk,krt
    ];
    var flightPath2 = new google.maps.Polyline({
      path: flightPlanCoordinates2,
      strokeColor: "#29FF29",
      strokeOpacity: 1.0,
      strokeWeight: 2
    });
	
    flightPath2.setMap(map);	
	
  }
	
</script>
<script type="text/javascript">
  $(document).ready( function() {
    initialize();
  });
</script>
<?php if ($x != 0 and $y != 0){ ?>
<div id="map_canvas" style="margin-left:10px; width:98%; height:450px;">
	Harap perikasa koneksi internet anda.
</div>
<?php }else{ ?>
	
	
<?php } ?>

This Page is Rendered {elapsed_time} seconds
