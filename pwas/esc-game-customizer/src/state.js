/**
 * EventManager dispatches events that will update nextState
 * ReducerManager combines state and nextState
 */

import {get, orderBy} from "lodash-es";
import { ReducerManager, EventManager } from "@esc_games/esc-react-redux";

import {
  EVENT_NAMESPACE,
  ACTION_FIELD_CHANGED,
  ACTION_SET_SAVING,
  ACTION_SET_PUBLISHING,
  ACTION_SET_PUBLISHABLE,
  ACTION_SET_ESC,
  ACTION_SET_VTT_DATA,
  ACTION_SET_EXITING,
  ACTION_SET_SAVE_STATUS,
} from "./actions";

/**
 * Returns a function which:
 *   Stores a boolean value of `key` in state,
 *   boolean value is enforced
 * @param  {String} key Key in state
 * @return {Function}
 */
const booleanReducer = key => (state, action) => ({
  ...state,
  [key]: !!action.value,
});

/**
 * Returns a function which:
 *   Merges state and nextState at `key`
 * @param  {String} key Key in state
 * @return {Function}
 */
const spreadByKeyReducer = key => (state, action) => ({
  ...state,
  [key]: {
    ...state[key],
    ...action.value,
  },
});

/**
 * Returns a function which:
 *   Assigns the value of the action to `key`
 * @param  {String} key Key in state
 * @return {Function}
 */
const assignByKeyReducer = key => (state, action) => ({
  ...state,
  [key]: action.value,
})

const reducerManager = new ReducerManager(
  {
    [ACTION_SET_ESC]: (state, action) => {
      const escInstance = action.value;
      const brandingForm = orderBy(get(escInstance, "page.branding_form", []), "modOrder");
      const gameMod = get(escInstance, "page.game_mod", {});
      const gameModBuild = get(escInstance, "page.game_mod_build", {});
      const gameModData = get(escInstance, "page.game_mod_data", {});
      const gameBuild = get(escInstance, "page.game_build", {});
      const gameControllers = get(escInstance, "page.game_build.game_controllers", {});
      const gamePhases = get(escInstance, "page.game_phases", []);

      const fields = brandingForm.reduce(
        (memo, field) => ({
          ...memo,
          [field.name]: {
            ...field,
            changed: false
          }
        }),
        {}
      );

      return {
        ...state,
        brandingForm,
        gameMod,
        gameModBuild,
        gameModData,
        gameBuild,
        gameControllers,
        gamePhases,
        fields,
      };
    },
    [ACTION_SET_PUBLISHING]: booleanReducer("publishing"),
    [ACTION_SET_PUBLISHABLE]: booleanReducer("publishable"),
    [ACTION_SET_SAVING]: booleanReducer("saving"),
    [ACTION_SET_EXITING]: booleanReducer("exiting"),
    [ACTION_SET_SAVE_STATUS]: assignByKeyReducer("saveStatus"),
    [ACTION_FIELD_CHANGED]: spreadByKeyReducer("fields"),
    [ACTION_SET_VTT_DATA]: spreadByKeyReducer("vttData"),
  },
  {
    fields: {},
    vttData: {},
    publishable: true
  }
);

const eventManager = new EventManager(
  EVENT_NAMESPACE,
  reducerManager
);

export {
  reducerManager, eventManager
};
