export async function a({ page, B, shot }) {
  await page.goto(B+'/seances'); await shot('01_list_top', false); await shot('01_list_full');
  console.log((await page.innerText('main')).slice(0,1500));
  await page.goto(B+'/seances/nouvelle'); await shot('02_create_free_top', false); await shot('02_create_free_full');
  console.log((await page.innerText('main')).slice(0,1500));
}
