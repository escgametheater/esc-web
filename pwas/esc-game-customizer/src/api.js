import axios from "axios";
import { get } from "lodash-es";

export const getUrl = path => window.location.href.replace(/\/customizer(\/content|\/branding)?$/i, path);
const isValidResponse = r => r.status === 200;
const unwrapData = r => {
  if (!isValidResponse(r)) {
    throw new Error(r);
  }

  return r.data;
}

export default {
  uploadSlug(slug, filename, blob, gameModBuildId) {
    const data = new FormData();
    data.append("slug", slug);
    data.append("replace", "on");
    data.append("is_public", "on");
    data.append(
      "game_asset_file",
      blob,
      // "powered-by-logo.png"
      filename
    );

    const uploadUrl = getUrl(`/dev/upload-custom-game-mod-asset/${
        slug
      }/?game_mod_build_id=${gameModBuildId}`);

    return axios.post(uploadUrl, data)
      .then(unwrapData)
      .catch(e => {
        console.log("FAILED TO upload slug", e);
      })
  },
  uploadCustomData(key, blob, gameModBuildId) {
    const data = new FormData();
    data.append("key", key);
    data.append("upload_id_file", `mod-file-${new Date().getTime()}`);
    data.append(
      "file",
      blob,
      // "file.xlsx"
      // @todo pass, and track file name
      `${key}.xlsx`
    );

    const uploadUrl = getUrl(`/dev/upload-custom-game-mod-data/${
        key
      }/?game_mod_build_id=${gameModBuildId}`);

    return axios.post(uploadUrl, data)
      .then(unwrapData)
      .catch(e => {
        console.log("FAILED TO upload custom data", e);
      })
  },
  publish(gameModBuildId) {
    const publishUrl = getUrl("/dev/publish-mod-to-live/" + gameModBuildId);

    return axios.post(publishUrl, {
      game_mod_build_id: gameModBuildId,
      is_active: 1,
      next: window.esc.page.next,
    }).then(({data}) => {
      console.log("From publish ... data", JSON.stringify(data));
      const nextUrl = get(data, "data.nextUrl", false);
      if (nextUrl) {
        window.location.href = nextUrl;
        return;
      }
      
      console.log("PUBLISH FAILED?", data);

      return data;
    }).catch(e => {
      console.log("FAILED TO PUBLISH", e);
    })
  },
  save(fieldToValueMap) {
    const saveUrl = getUrl("/customizer");
    return axios
      .post(saveUrl, fieldToValueMap)
      .then(unwrapData)
      .catch(e => {
        console.error("Failed to save fields", e);
      });
  }
}
