import React, { PureComponent } from "react";
import { Box, ThemeProvider, createTheme } from "mineral-ui";
import { BrowserRouter as Router } from "react-router-dom";
import GoogleFontLoader from "react-google-font-loader";
import { get } from "lodash-es";
import axios from "axios";

import LinearProgress from '@material-ui/core/LinearProgress';

import { eventManager } from "./state";
import {
  ACTION_FIELD_CHANGED,
  ACTION_SET_SAVING,
  ACTION_SET_PUBLISHING,
  ACTION_SET_PUBLISHABLE,
  ACTION_SET_ESC,
  ACTION_SET_VTT_DATA,
  ACTION_SET_EXITING,
  ACTION_SET_SAVE_STATUS,
} from "./actions";

import Header from "./components/Header";
import Exiting from "./components/Exiting";
import Routes from "./routes";
import LoadingGrid from "./components/LoadingGrid";
import api from "./api";

import "./ESCGameCustomizer.css";


const theme = createTheme({
  //   colors: {
  //     theme: {
  // [10]: "#afe9fe",
  // [20]: "#7ddbfd",
  // [30]: "#4bcdfc",
  // [40]: "#19befb",
  // [50]: "#04a3dd",
  // [60]: "#037eab",
  // [70]: "#013447",
  // [80]: "#000f15",
  // [90]: "#000000",
  // [100]: "#000000",
  //     }
  //   },
  overrides: {
    borderRadius_1: "6px",
    borderColor: "#CDD4DA",
    // backgroundColor_themePrimary: "#037EAB",
    // backgroundColor_themePrimary_active: "#024760",

    Text_fontSize_h1: "1.5rem",
    Text_fontSize_h6: ".75rem",
    Text_color_h1: "#000000",
    Text_color_h4: "#000000",
    Text_fontWeight_h1: "normal",
    Text_fontWeight_h4: "normal",
    Text_marginBottom_heading: "1.5rem",

    Text_lineHeight_heading: "22px",
    Text_lineHeight_headingSmall: "2.5rem",

    CardRow_paddingHorizontal: "2rem",
    CardRow_marginVertical: "2rem",

    TableHeaderCell_borderVertical: "0",
    TableHeader_borderTop: "0",
    TableHeader_borderBottom: "0",
    PrimaryNavItem_backgroundColor_selected: "transparent",
    PrimaryNavItem_backgroundColor_hover: "transparent",
    PrimaryNavItem_backgroundColor_active: "transparent",
    PrimaryNavItem_borderColor_selected: "#097CBE",
    PrimaryNavItem_borderColor_hover: "#097CBE",
    PrimaryNavItem_borderColor_active: "#097CBE",
    PrimaryNavItem_borderColor_focus: "#097CBE",
    PrimaryNavItem_color: "#BFC4CA",
    Button_borderWidth: "3px",
    Button_borderRadius: "3px",
    Button_borderBorderColor: "#63676D",
    Button_backgroundColor: "transparent",
    Button_backgroundColor_primary: "#097CBE",
    Button_color: "#1F2124",
    Button_fontWeight: "bold",
    Button_backgroundColor_active: "transparent",
    Button_backgroundColor_focus: "transparent",
    Button_backgroundColor_hover: "transparent",
  }
});

const customizerPath = window.location.pathname.substr(
  0,
  window.location.pathname.lastIndexOf("/customizer") + "/customizer".length
);
// reducerManager, eventManager
class ESCGameCustomizer extends PureComponent {
  constructor(props) {
    super(props);

    eventManager.dispatchUIDirect(ACTION_SET_ESC, props.esc);

    // Load VTT Previews
    const gameAssets = get(props.esc, "page.game_build.custom_game_assets", {});
    gameAssets
      .filter(cga => cga.slug.startsWith("preview-") && cga.slug.endsWith("-vtt") && cga.extension === "vtt")
      .map(cga => ({
        ...cga,
        controllerType: cga.slug.replace(/preview\-((cloud\-)?(player|join|custom|admin|spectator))\-vtt/, "$1")
      }))
      .forEach(cga => 
        axios
          .get(cga.public_url)
          .then(r => {
            eventManager.dispatchUIDirect(ACTION_SET_VTT_DATA, {
              [cga.controllerType]: `data:text/vtt,${r.data}`,
            });
          })
      )
  }

  handleFieldChange = (field, value) => {
    console.log("handleFieldChange", field, value);
    eventManager.dispatchUI(ACTION_FIELD_CHANGED, {
      [field.name]: {
        ...field,
        value,
        changed: true
      }
    });
  };

  // @todo generic-ify
  handleCustomDataChange = (key, file) => {
    eventManager.dispatchUI(ACTION_FIELD_CHANGED, {
      [`custom-data-${key}`]: {
        value: file,
        changed: true,
        name: `custom-data-${key}`,
      },
    });
  }

  handleSave = async () => {
    const currentFields = this.props.fields;
    const anyChanged = Object.values(currentFields).reduce(
      (memo, f) => (memo |= f.changed),
      false
    );

    if (!anyChanged) {
      console.log("No fields changed", currentFields);
      return;
    }

    eventManager.dispatchUIDirect(ACTION_SET_SAVING, true);

    // console.log(this.props);
    const fieldToValueMap = this.props.brandingForm.reduce(
      (memo, field) => ({
        ...memo,
        [field.name]: currentFields[field.name]
          ? currentFields[field.name].value
          : field.value
      }),
      {}
    );

    // Merge custom-data into fieldToValueMap
    Object.keys(this.props.fields)
      .filter(f => !!~f.indexOf("custom-data-"))
      .forEach(f => {
        fieldToValueMap[f] = this.props.fields[f].value;
      });

    const whenUploadsDone = [];
    Object.keys(fieldToValueMap).forEach(k => {
      if (!!~k.indexOf("poweredByLogo")) {
        const field = this.props.brandingForm.find(f => f.name === k);

        whenUploadsDone.push(
          this.props.fields[k].changed
            ? this.handleUploadSlug(field, fieldToValueMap[k])
            : Promise.resolve()
        );

        // Don't change the powered-by-logo slug value
        fieldToValueMap[k] = field.value; // "powered-by-logo";
      }
      else if (!!~k.indexOf("custom-data-")) {
        const file = fieldToValueMap[k];
        const key = k;

        whenUploadsDone.push(
          this.props.fields[k].changed
          ? this.handleUploadCustomData(key.replace(/^custom-data-/i, ""), file)
          : Promise.resolve()
        );
      }
    });

    await Promise.all(whenUploadsDone);

    await api.save(fieldToValueMap)
      .then(d => {
        console.log("Save success", d);

        // @todo Move this to the reducer
        eventManager.dispatchUIDirect(ACTION_SET_ESC, {
          ...this.props.esc,
          page: {
            ...this.props.esc.page,
            ...d            
          }
        });

        const fields = { ...this.props.fields };
        Object.keys(fields).forEach(k => (fields[k].changed = false));
        eventManager.dispatchUIDirect(ACTION_SET_SAVING, false);
        eventManager.dispatchUIDirect(ACTION_FIELD_CHANGED, fields);
        eventManager.dispatchUIDirect(ACTION_SET_PUBLISHABLE, true);
        eventManager.dispatchUIDirect(ACTION_SET_SAVE_STATUS, true);

        // Clear save status
        setTimeout(() => {
          eventManager.dispatchUIDirect(ACTION_SET_SAVE_STATUS, undefined);
        }, 1000);
      })
      .catch(e => {
        console.error("Save failed for reasons ... ", e);
        eventManager.dispatchUIDirect(ACTION_SET_SAVE_STATUS, false);
      })
  };

  handleUploadSlug = (field, blob) => {
    const slug = field.value;
    const filename = get(field, "properties.file_meta.filename", field.value);
    const gameModBuildId = get(this.props, "gameModBuild.id", -1);

    return api.uploadSlug(slug, filename, blob, gameModBuildId);
  };

  handleUploadCustomData = (key, blob) => {
    const gameModBuildId = get(this.props, "gameModBuild.id", -1);
    return api.uploadCustomData(key, blob, gameModBuildId);
  }

  handlePublish = async () => {

    await this.handleSave();

    const gameModBuildId = get(this.props, "gameModBuild.id", -1);
    
    eventManager.dispatchUIDirect(ACTION_SET_PUBLISHING, true);

    return api.publish(gameModBuildId)
      .then(() => {
        eventManager.dispatchUIDirect(ACTION_SET_PUBLISHING, false);
      });
  }

  handleSaveAndExit = async () => {
    await this.handleSave();
    // @todo capture failure needed?
    this.handleExit();
  }

  handleExit = () => {
    eventManager.dispatchUIDirect(ACTION_SET_EXITING, true);
    window.location.href=atob(window.esc.page.next);
  }

  render() {
    const { gameMod, gameModBuild } = this.props;

    return (
      <ThemeProvider theme={theme}>
        {!gameMod ? (
          <Box marginTop="50vh">
            <LoadingGrid />
          </Box>
        ) : (
          <Router basename={customizerPath}>
            <GoogleFontLoader
              fonts={[
                {
                  font: "Open Sans",
                  weights: [
                    "300",
                    "300i",
                    "400",
                    "400i",
                    "600",
                    "600i",
                    "700",
                    "700i",
                    "800",
                    "800i"
                  ]
                }
              ]}
            />
            <Header
              logo={
                <img
                  id={"game-logo-image"}
                  srcSet={`${gameMod.game.avatar.small_url} 320w, ${
                    gameMod.game.avatar.medium_url
                  } 480w, ${gameMod.game.avatar.big_url} 800w`}
                  sizes="(max-width: 320px) 280px, (max-width: 480px) 440px, 800px"
                  src={gameMod.game.avatar.big_url}
                  alt="game-logo"
                  height="54"
                />
              }
              onClickSave={this.handleSave}
              onClickPublish={this.handlePublish}
              onClickSaveAndExit={this.handleSaveAndExit}
              onClickExit={this.handleExit}
              saving={this.props.saving}
              saveStatus={this.props.saveStatus}
              publishable={gameModBuild.published_time == null && gameModBuild.update_channel == "dev" || this.props.publishable}
              publishing={this.props.publishing}
            />
            {
              (this.props.exiting || this.props.saving || this.props.publishing) && (
                <Box>
                  <LinearProgress />
                </Box>
              )
            }
            <Box paddingVertical="2rem" paddingHorizontal="4rem">
              <Routes
                {...this.props}
                fields={Object.values(this.props.fields)}
                onFieldChange={this.handleFieldChange}
                onCustomDataChange={this.handleCustomDataChange}
              />
            </Box>
          </Router>

        )}
      </ThemeProvider>
    );
  }
}

export default eventManager.connect(ESCGameCustomizer, [
  ACTION_FIELD_CHANGED
]);
